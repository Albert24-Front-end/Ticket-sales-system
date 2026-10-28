<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayContract;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Payment;
use App\Models\PaymentStatus;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayContract $paymentGateway,
        private readonly OrderService $orderService,
        private readonly SignatureContract $signatureService,
    )
    {}

    public function initiatePayment(Order $order): Payment
    {
        if ($order->status !== OrderStatus::PENDING) {
            throw ValidationException::withMessages([
                "order" => "Payment can only be created for a pending order.",
            ]);
        }
        // если платёж уже успешно инициирован, повторно внешний create-payment не вызываем.
        $existingPayment = $order->payment;
        if (
            $existingPayment !== null
            && $existingPayment->payment_url !== null
        ) {
            return $existingPayment;
        }

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

    public function processWebhook()
    {
        // проверить HMAC, найти именно тот локальный Payment, который соответствует order_id, проверить сумму, а затем идемпотентно провести переход PENDING → PAID/FAILED

    }
}
