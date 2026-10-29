<?php

namespace Tests\Feature;

use App\Contracts\AuditLogContract;
use App\Contracts\PaymentGatewayContract;
use App\Data\Payment\PaymentResultData;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Payment;
use App\Models\PaymentStatus;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\PaymentAmountConverter;
use App\Services\Payments\PaymentService;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            "payment.cashbox_id" => 3,
            "payment.secret" => "test-secret",
        ]);
    }

    private function createOrder(OrderStatus $status = OrderStatus::PENDING): Order
    {
        $user = User::factory()->create();

        $event = Event::factory()->create([
            "organizer_id" => $user->id,
            "status" => EventStatus::PUBLISHED,
        ]);

        return Order::create([
            "user_id" => $user->id,
            "event_id" => $event->id,
            "total_amount" => 450_000,
            "status" => $status,
        ]);
    }

    private function makeService(PaymentGatewayContract $gateway): PaymentService
    {
        $auditLog = Mockery::mock(AuditLogContract::class);

        $orderService = new OrderService($auditLog);

        return new PaymentService(
            $gateway,
            $orderService,
            new SignatureService(),
            new PaymentAmountConverter(),
        );
    }

    public function testInitiatePaymentCreatesLocalPaymentAndStoresUrl(): void
    {
        $order = $this->createOrder();

        $gateway = Mockery::mock(
            PaymentGatewayContract::class
        );

        $gateway
            ->shouldReceive("createPayment")
            ->once()
            ->with(
                Mockery::on(
                    fn (Order $gatewayOrder) =>
                    $gatewayOrder->is($order)
                )
            )
            ->andReturn(
                new PaymentResultData(
                    paymentUrl:
                    "https://payments.test/process-payment/10"
                )
            );

        $service = $this->makeService($gateway);

        $payment = $service->initiatePayment(
            $order
        );

        $this->assertDatabaseHas("payments", [
            "id" => $payment->id,
            "order_id" => $order->id,
            "cashbox_id" => 3,
        // В локальной БД merchant сумма остаётся в UZS.
            "amount" => 450_000,
            "status" => PaymentStatus::PENDING->value,
            "payment_url" =>
                "https://payments.test/process-payment/10",
        ]);
    }

    public function testExistingSuccessfulPaymentIsReturnedWithoutCallingGatewayAgain(): void
    {
        $order = $this->createOrder();

        $payment = Payment::create([
            "order_id" => $order->id,
            "cashbox_id" => 3,
            "amount" => $order->total_amount,
            "status" => PaymentStatus::PENDING,
            "payment_url" =>
                "https://payments.test/process-payment/10",
        ]);

        $gateway = Mockery::mock(
            PaymentGatewayContract::class
        );

        $gateway->shouldNotReceive("createPayment");

        $service = $this->makeService($gateway);

        $result = $service->initiatePayment($order);

        $this->assertTrue($result->is($payment));
    }

    public function testCannotInitiatePaymentForNonPendingOrder(): void
    {
        $order = $this->createOrder(OrderStatus::PAID);

        $gateway = Mockery::mock(PaymentGatewayContract::class);

        $gateway->shouldNotReceive("createPayment");

        $service = $this->makeService($gateway);

        $this->expectException(ValidationException::class);

        $service->initiatePayment($order);
    }

    public function testDoesNotCreateSecondExternalPaymentWhenLocalPaymentAlreadyExistsWithoutUrl(): void
    {
        $order = $this->createOrder();

        Payment::create([
            "order_id" => $order->id,
            "cashbox_id" => 3,
            "amount" => $order->total_amount,
            "status" => PaymentStatus::PENDING,
            "payment_url" => null,
        ]);

        $gateway = Mockery::mock(
            PaymentGatewayContract::class
        );

        $gateway
            ->shouldNotReceive("createPayment");

        $service = $this->makeService($gateway);

        $this->expectException(
            ValidationException::class
        );

        $service->initiatePayment($order);
    }

    public function testGatewayFailureLeavesLocalPaymentPendingForReconciliation(): void
    {
        $order = $this->createOrder();

        $gateway = Mockery::mock(
            PaymentGatewayContract::class
        );

        $gateway
            ->shouldReceive("createPayment")
            ->once()
            ->andThrow(
                new RuntimeException(
                    "Payment system unavailable"
                )
            );

        $service = $this->makeService($gateway);

        try {
            $service->initiatePayment($order);

            $this->fail(
                "Expected gateway exception was not thrown."
            );
        } catch (RuntimeException $exception) {
            $this->assertEquals(
                "Payment system unavailable",
                $exception->getMessage()
            );
        }

        // намеренно не удаляем Payment и не отменяем Order. Внешняя PSP могла создать Payment, а HTTP response мог потеряться.
        $this->assertDatabaseHas("payments", [
            "order_id" => $order->id,
            "cashbox_id" => 3,
            "amount" => 450_000,
            "status" =>
                PaymentStatus::PENDING->value,
            "payment_url" => null,
        ]);

        $this->assertDatabaseHas("orders", [
            "id" => $order->id,
            "status" =>
                OrderStatus::PENDING->value,
        ]);
    }
}
