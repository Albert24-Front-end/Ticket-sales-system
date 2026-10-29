<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayContract;
use App\Contracts\SignatureContract;
use App\Data\Payment\PaymentWebhookData;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Payment;
use App\Models\PaymentStatus;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayContract $paymentGateway,
        private readonly OrderService $orderService,
        private readonly SignatureContract $signatureService,
        private readonly PaymentAmountConverter $converter
    )
    {}

    public function initiatePayment(Order $order): Payment
    {
        if ($order->status !== OrderStatus::PENDING) {
            throw ValidationException::withMessages([
                "order" => "Payment can only be created for a pending order.",
            ]);
        }
        // если платёж уже успешно инициирован, повторно внешний create-payment не вызываем
        $existingPayment = $order->payment;
        if (
            $existingPayment !== null
            && $existingPayment->payment_url !== null
        ) {
            return $existingPayment;
        }

        // внешний payment создан, но payment_url не был получен - снова вызывать PS create payment рискованно
        if ($existingPayment !== null) {
            throw ValidationException::withMessages([
                "payment" =>
                    "Payment creation has already been started for this order.",
            ]);
        }

        $payment = Payment::create([
            "order_id" => $order->id,
            "cashbox_id" => (int) config("payment.cashbox_id"),
            "amount" => $order->total_amount,
            "status" => PaymentStatus::PENDING,
        ]);

        $result = $this->paymentGateway->createPayment($order);

        $payment->update([
            "payment_url" => $result->paymentUrl,
        ]);
        return $payment->refresh();
    }

    public function processWebhook(PaymentWebhookData $data, string $signature): Payment
    {
        // проверить HMAC, найти именно тот локальный Payment, который соответствует order_id, проверить сумму, а затем идемпотентно провести переход PENDING → PAID/FAILED
        $payload = [
            "order_id" => $data->order_id,
            "amount" => $data->amount,
            "status" => $data->status,
        ];

        // проверяем, что подпись соответствует ожидаемому стандарту - 64 lowercase hex symbols
        if (preg_match("/\A[0-9a-f]{64}\z/", $signature) !== 1) {
            throw new HttpException(400, "Invalid webhook signature");
        }

        $signatureIsValid = $this->signatureService->checkSignature($payload, (string) config("payment.secret"), $signature);

        if (!$signatureIsValid) {
            throw new HttpException(401, "Invalid webhook signature");
        }

        // вводим внешнюю транзакцию - это дает атомарность/неразрывность всех изменений Payment - Order - TicketType
        return DB::transaction(function () use ($data) {
            // внешний order_id должен соотв-ть внутр. order_id, проверяем также cashbox_id - order_id уникален только внутри кассы
            $payment = Payment::query()
                ->where("order_id", $data->order_id)
                ->where("cashbox_id", (int) config("payment.cashbox_id"))
                ->lockForUpdate()
                ->firstOrFail();

            // проверяем сумму amount (внешний payload->amount в его единице) - совпадение order_id не означает мгновенного принятия status:paid
            $expectedPaymentAmount = $this->converter->toTiyin((int) $payment->amount);
            if ($expectedPaymentAmount !== $data->amount) {
                throw ValidationException::withMessages([
                    "amount" => "Webhook amount does not match the payment amount",
                ]);
            }

            $order = $payment->order;
            $expectedOrderAmount = $this->converter->toTiyin((int) $order->total_amount);
            if ($expectedOrderAmount !== $data->amount) {
                throw ValidationException::withMessages([
                    "amount" => "Webhook amount does not match the order amount",
                ]);
            }
            // просто возвращаем платеж без изменений (status 0 - pending), подтверждаем принятие вебхука
            if ($data->status === 0) {
                return $payment;
            }

            $incomingStatus = match($data->status) {
                1 => PaymentStatus::PAID,
                2 => PaymentStatus::FAILED,
            };

            // вводим идемпотентность при получении вебхуков
            if ($payment->status === $incomingStatus) {
                return $payment;
            }
            // платеж уже завершен успешно/неспешно - не меняем его статус, выдаем ошибку
            if ($payment->status !== PaymentStatus::PENDING) {
                throw new HttpException(409, "Conflicting payment status");
            }

            if ($incomingStatus === PaymentStatus::PAID) {
                $this->orderService->markOrderAsPaid($order);
            }

            if ($incomingStatus === PaymentStatus::FAILED) {
                $this->orderService->cancelOrder($order);
            }

            // после успешной обработки Order можем фиксировать окончание Payment
            // все происходит во внешней транзакции, благодаря этому при ошибке все изменения откатятся
            $payment->update([
                "status" => $incomingStatus,
            ]);
            return $payment->refresh();
        });
    }
}
