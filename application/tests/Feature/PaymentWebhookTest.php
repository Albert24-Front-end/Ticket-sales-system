<?php

namespace Tests\Feature;

use App\Contracts\SignatureContract;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Payment;
use App\Models\PaymentStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    // идет по настоящему Laravel route POST /api/payments/webhook, проверяет FormRequest
    //→ DTO
    //→ HMAC
    //→ PaymentService
    //→ OrderService
    //→ Payment/Order/TicketType

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            "payment.cashbox_id" => 3,
            "payment.secret" => "merchant-test-secret",
        ]);
    }

    private function createScenario(
        int $ticketQuantity = 2,
        int $unitPrice = 100_000,
    ): array {
        $user = User::factory()->create();

        $event = Event::factory()->create([
            "organizer_id" => $user->id,
            "status" => EventStatus::PUBLISHED,
        ]);

        $ticketType = TicketType::factory()->create([
            "event_id" => $event->id,
            "price" => $unitPrice,
            "quantity" => 100,
            "sold_quantity" => 0,
            "reserved_quantity" =>
                $ticketQuantity,
        ]);

        $order = Order::create([
            "user_id" => $user->id,
            "event_id" => $event->id,

            "total_amount" =>
                $unitPrice * $ticketQuantity,

            "status" => OrderStatus::PENDING,
        ]);

        $order->orderItems()->create([
            "ticket_type_id" =>
                $ticketType->id,

            "quantity" =>
                $ticketQuantity,

            "unit_price" =>
                $unitPrice,
        ]);

        $payment = Payment::create([
            "order_id" => $order->id,
            "cashbox_id" => 3,
            "amount" => $order->total_amount,
            "status" =>
                PaymentStatus::PENDING,

            "payment_url" =>
                "https://payments.test/process-payment/1",
        ]);

        return [
            $order,
            $payment,
            $ticketType,
        ];
    }

    private function makePayload(
        Order $order,
        int $status,
        ?int $amountTiyin = null,
    ): array {
        return [
            "order_id" =>
                (string) $order->id,
            // Merchant хранит UZS. PSP webhook присылает tiyin.
            "amount" =>
                $amountTiyin
                ?? ((int) $order->total_amount * 100),

            "status" => $status,
        ];
    }

    private function sign(
        array $payload
    ): string {
        return app(
            SignatureContract::class
        )->sign(
            $payload,
            (string) config("payment.secret")
        );
    }

    private function sendWebhook(
        array $payload,
        ?string $signature = null,
    ) {
        return $this->postJson(
            "/api/payments/webhook",
            $payload,
            [
                "X-Signature" =>
                    $signature
                    ?? $this->sign($payload),
            ]
        );
    }

    public function testPaidWebhookMarksPaymentAndOrderAsPaid(): void
    {
        [$order, $payment, $ticketType] =
            $this->createScenario(
                ticketQuantity: 2,
                unitPrice: 100_000,
            );

        $payload = $this->makePayload(
            $order,
            status: 1,
        );

        $response = $this->sendWebhook(
            $payload
        );

        $response->assertNoContent();

        $this->assertDatabaseHas("payments", [
            "id" => $payment->id,
            "status" =>
                PaymentStatus::PAID->value,
        ]);

        $this->assertDatabaseHas("orders", [
            "id" => $order->id,
            "status" =>
                OrderStatus::PAID->value,
        ]);

        $ticketType->refresh();

        $this->assertEquals(
            0,
            $ticketType->reserved_quantity
        );

        $this->assertEquals(
            2,
            $ticketType->sold_quantity
        );
    }

    public function testDuplicatePaidWebhookIsIdempotent(): void
    {
        [$order, $payment, $ticketType] =
            $this->createScenario(
                ticketQuantity: 2,
            );

        $payload = $this->makePayload(
            $order,
            status: 1,
        );

        $signature = $this->sign(
            $payload
        );

        $this->sendWebhook(
            $payload,
            $signature
        )->assertNoContent();


        // PSP повторяет тот же webhook
        $this->sendWebhook(
            $payload,
            $signature
        )->assertNoContent();

        $ticketType->refresh();

        $this->assertEquals(
            0,
            $ticketType->reserved_quantity
        );

        // не 4.
        $this->assertEquals(
            2,
            $ticketType->sold_quantity
        );

        $this->assertDatabaseHas("payments", [
            "id" => $payment->id,
            "status" =>
                PaymentStatus::PAID->value,
        ]);
    }

    public function testFailedWebhookCancelsOrderAndReleasesReservation(): void
    {
        [$order, $payment, $ticketType] =
            $this->createScenario(
                ticketQuantity: 2,
            );

        $payload = $this->makePayload(
            $order,
            status: 2,
        );

        $this->sendWebhook($payload)
            ->assertNoContent();

        $this->assertDatabaseHas("payments", [
            "id" => $payment->id,
            "status" =>
                PaymentStatus::FAILED->value,
        ]);

        $this->assertDatabaseHas("orders", [
            "id" => $order->id,
            "status" =>
                OrderStatus::CANCELLED->value,
        ]);

        $ticketType->refresh();

        $this->assertEquals(
            0,
            $ticketType->reserved_quantity
        );

        $this->assertEquals(
            0,
            $ticketType->sold_quantity
        );
    }

    public function testDuplicateFailedWebhookIsIdempotent(): void
    {
        [$order, $payment, $ticketType] =
            $this->createScenario(
                ticketQuantity: 2,
            );

        $payload = $this->makePayload(
            $order,
            status: 2,
        );

        $signature = $this->sign(
            $payload
        );

        $this->sendWebhook(
            $payload,
            $signature
        )->assertNoContent();

        $this->sendWebhook(
            $payload,
            $signature
        )->assertNoContent();

        $ticketType->refresh();

        // Не должно стать -2.
        $this->assertEquals(
            0,
            $ticketType->reserved_quantity
        );

        $this->assertEquals(
            0,
            $ticketType->sold_quantity
        );

        $this->assertDatabaseHas("payments", [
            "id" => $payment->id,
            "status" =>
                PaymentStatus::FAILED->value,
        ]);
    }

    public function testWebhookWithInvalidSignatureIsRejected(): void
    {
        [$order, $payment, $ticketType] =
            $this->createScenario();

        $payload = $this->makePayload(
            $order,
            status: 1,
        );


        // Формат подписи корректный: 64 lowercase hex. Но сама HMAC неверна.
        $response = $this->sendWebhook(
            $payload,
            str_repeat("a", 64)
        );

        $response->assertStatus(401);

        $this->assertDatabaseHas("payments", [
            "id" => $payment->id,
            "status" =>
                PaymentStatus::PENDING->value,
        ]);

        $this->assertDatabaseHas("orders", [
            "id" => $order->id,
            "status" =>
                OrderStatus::PENDING->value,
        ]);

        $ticketType->refresh();

        $this->assertEquals(
            2,
            $ticketType->reserved_quantity
        );

        $this->assertEquals(
            0,
            $ticketType->sold_quantity
        );
    }

    public function testWebhookWithoutSignatureIsRejected(): void
    {
        [$order] = $this->createScenario();

        $payload = $this->makePayload(
            $order,
            status: 1,
        );

        $response = $this->postJson(
            "/api/payments/webhook",
            $payload
        );

        $response->assertStatus(401);
    }

    public function testWebhookWithWrongAmountIsRejected(): void
    {
        [$order, $payment, $ticketType] =
            $this->createScenario();

        // Отправляем другую сумму, не 20_000_000 tiyin.

        $payload = $this->makePayload(
            $order,
            status: 1,
            amountTiyin: 19_000_000,
        );

        // ВАЖНО: подписываем именно неправильный payload. Поэтому HMAC проходит и тест действительно доходит до проверки суммы
        $response = $this->sendWebhook(
            $payload
        );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            "amount",
        ]);

        $this->assertDatabaseHas("payments", [
            "id" => $payment->id,
            "status" =>
                PaymentStatus::PENDING->value,
        ]);

        $this->assertDatabaseHas("orders", [
            "id" => $order->id,
            "status" =>
                OrderStatus::PENDING->value,
        ]);

        $ticketType->refresh();

        $this->assertEquals(
            2,
            $ticketType->reserved_quantity
        );
    }

    public function testWebhookForUnknownPaymentReturns404(): void
    {
        $payload = [
            "order_id" => "999999",
            "amount" => 10_000_000,
            "status" => 1,
        ];

        $response = $this->sendWebhook(
            $payload
        );

        $response->assertNotFound();
    }

    public function testPendingWebhookDoesNotChangeAnything(): void
    {
        [$order, $payment, $ticketType] =
            $this->createScenario();

        $payload = $this->makePayload(
            $order,
            status: 0,
        );

        $this->sendWebhook($payload)
            ->assertNoContent();

        $this->assertDatabaseHas("payments", [
            "id" => $payment->id,
            "status" =>
                PaymentStatus::PENDING->value,
        ]);

        $this->assertDatabaseHas("orders", [
            "id" => $order->id,
            "status" =>
                OrderStatus::PENDING->value,
        ]);

        $ticketType->refresh();

        $this->assertEquals(
            2,
            $ticketType->reserved_quantity
        );

        $this->assertEquals(
            0,
            $ticketType->sold_quantity
        );
    }

    public function testConflictingFinalWebhookIsRejected(): void
    {
        [$order, $payment, $ticketType] =
            $this->createScenario();

        // Сначала успешно оплачиваем

        $paidPayload = $this->makePayload(
            $order,
            status: 1,
        );

        $this->sendWebhook(
            $paidPayload
        )->assertNoContent();

        // Потом приходит противоречивый FAILED

        $failedPayload = $this->makePayload(
            $order,
            status: 2,
        );

        $response = $this->sendWebhook(
            $failedPayload
        );

        $response->assertStatus(409);


         // Состояние не должно откатиться обратно.
        $this->assertDatabaseHas("payments", [
            "id" => $payment->id,
            "status" =>
                PaymentStatus::PAID->value,
        ]);

        $this->assertDatabaseHas("orders", [
            "id" => $order->id,
            "status" =>
                OrderStatus::PAID->value,
        ]);

        $ticketType->refresh();

        $this->assertEquals(
            0,
            $ticketType->reserved_quantity
        );

        $this->assertEquals(
            2,
            $ticketType->sold_quantity
        );
    }

    public function testWebhookWithUnexpectedFieldIsRejected(): void
    {
        [$order] = $this->createScenario();

        $payload = $this->makePayload(
            $order,
            status: 1,
        );


         // Нарушаем additionalProperties:false.
        $payload["payment_id"] = 123;


         // Здесь HMAC вообще не важна: FormRequest должен остановить запрос раньше.
        $response = $this->postJson(
            "/api/payments/webhook",
            $payload,
            [
                "X-Signature" =>
                    str_repeat("a", 64),
            ]
        );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            "payload",
        ]);
    }
}
