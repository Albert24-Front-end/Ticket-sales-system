<?php

namespace Tests\Unit;

use App\Data\Payment\PaymentResultData;
use App\Models\Order;
use App\Services\Payments\EventPaymentGateway;
use App\Services\Payments\PaymentAmountConverter;
use App\Services\SignatureService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EventPaymentGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            "payment.url" => "https://payments.test",
            "payment.cashbox_id" => 3,
            "payment.secret" => "test-secret",
        ]);

        Http::preventStrayRequests();
    }

    private function makeGateway(): EventPaymentGateway
    {
        return new EventPaymentGateway(
            new SignatureService(),
            new PaymentAmountConverter(),
        );
    }

    public function testGatewayCreatesExternalPayment(): void
    {
        Http::fake([
            "https://payments.test/api/create-payment" =>
                Http::response([
                    "data" => [
                        "url" =>
                            "https://payments.test/process-payment/123",
                    ],
                ], 201),
        ]);

        $order = new Order();

        $order->forceFill([
            "id" => 17,
            "total_amount" => 100_000,
        ]);

        $gateway = $this->makeGateway();

        $result = $gateway->createPayment($order);

        $this->assertInstanceOf(
            PaymentResultData::class,
            $result
        );

        $this->assertEquals(
            "https://payments.test/process-payment/123",
            $result->paymentUrl
        );

        // Merchant хранит 100 000 UZS. PSP должна получить 10 000 000 tiyin
        $expectedPayload = [
            "cashbox_id" => 3,
            "order_id" => "17",
            "description" => "Order #17",
            "amount" => 10_000_000,
        ];

         // Проверяем HMAC независимо от Gateway. После ksort: amount, cashbox_id, description, order_id
        $canonicalString =
            "amount|10000000"
            . "|cashbox_id|3"
            . "|description|Order #17"
            . "|order_id|17";

        $expectedSignature = hash_hmac(
            "sha256",
            $canonicalString,
            "test-secret"
        );

        Http::assertSent(
            function (Request $request) use (
                $expectedPayload,
                $expectedSignature
            ) {
                return
                    $request->url()
                    === "https://payments.test/api/create-payment"
                    && $request->method() === "POST"
                    && $request["cashbox_id"]
                    === $expectedPayload["cashbox_id"]
                    && $request["order_id"]
                    === $expectedPayload["order_id"]
                    && $request["description"]
                    === $expectedPayload["description"]
                    && $request["amount"]
                    === $expectedPayload["amount"]
                    && $request->hasHeader(
                        "X-Signature",
                        $expectedSignature
                    );
            }
        );

        Http::assertSentCount(1);
    }

    public function testGatewayThrowsExceptionWhenPaymentSystemReturnsError(): void
    {
        Http::fake([
            "https://payments.test/api/create-payment" =>
                Http::response([
                    "message" => "Invalid signature",
                ], 400),
        ]);

        $order = new Order();

        $order->forceFill([
            "id" => 17,
            "total_amount" => 100_000,
        ]);

        $this->expectException(
            RequestException::class
        );

        $this->makeGateway()
            ->createPayment($order);
    }

    public function testGatewayRejectsResponseWithoutPaymentUrl(): void
    {
        Http::fake([
            "https://payments.test/api/create-payment" =>
                Http::response([
                    "data" => [],
                ], 201),
        ]);

        $order = new Order();

        $order->forceFill([
            "id" => 17,
            "total_amount" => 100_000,
        ]);

        $this->expectException(
            \RuntimeException::class
        );

        $this->makeGateway()
            ->createPayment($order);
    }
}
