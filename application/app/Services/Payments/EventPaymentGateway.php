<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayContract;
use App\Contracts\SignatureContract;
use App\Data\Payment\PaymentResultData;
use App\Http\Requests\Order\OrderCreationRequest;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\SignatureService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EventPaymentGateway implements PaymentGatewayContract
{
    public function __construct(
        private readonly SignatureContract $signatureService,
    )
    {}
    public function createPayment(Order $order): PaymentResultData
    {
//        Это место для взаимодействия с внешним API:
//        формирования запроса, подписи, отправки HTTP и
//        преобразования ответа в PaymentResultData.

        // готовим данные и вызываем PS
        $data = [
            "cashbox_id" => (int) config("payment.cashbox_id"),
            "order_id" => (string) $order->id,
            "amount" => (int) $order->total_amount,
            "description" => "Order #{$order->id}",
        ];
        $signatureService = $this->signatureService;
        $signature = $signatureService->sign($data, config("payment.secret"));
        $response = Http::withHeaders([
            "Content-Type" => "application/json",
            "Accept" => "application/json",
            "X-Signature" => $signature,
        ])->post(config("payment.url") . "/api/create-payment", $data);

        $response->throw();
        $paymentUrl = $response->json("data.url");

        if (!is_string($paymentUrl) || $paymentUrl === "") {
            throw new RuntimeException(
                "Payment system returned invalid payment URL."
            );
        }

        return new PaymentResultData(
            paymentUrl: $paymentUrl,
        );

    }
}
