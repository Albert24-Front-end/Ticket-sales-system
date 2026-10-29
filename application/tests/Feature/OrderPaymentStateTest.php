<?php

namespace Tests\Feature;

use App\Contracts\AuditLogContract;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\TicketType;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use App\Events\OrderPaid;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\TestCase;

class OrderPaymentStateTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderService(): OrderService
    {
        $auditLog = Mockery::mock(AuditLogContract::class);
        return new OrderService($auditLog);
    }

    private function createPendingOrder(int $quantity = 3, int $price = 100_000, int $soldQuantity = 2): array
    {
        $user = User::factory()->create();

        $event = Event::factory()->create([
            "organizer_id" => $user->id,
            "status" => EventStatus::PUBLISHED,
        ]);

        $ticketType = TicketType::factory()->create([
            "event_id" => $event->id,
            "price" => $price,
            "quantity" => 100,
            "sold_quantity" => $soldQuantity,
            "reserved_quantity" => $quantity,
        ]);

        $order = Order::create([
            "user_id" => $user->id,
            "event_id" => $event->id,
            "total_amount" => $price * $quantity,
            "status" => OrderStatus::PENDING,
        ]);

        $order->orderItems()->create([
            "ticket_type_id" => $ticketType->id,
            "quantity" => $quantity,
            "unit_price" => $price,
        ]);

        return [
            $order,
            $ticketType,
        ];
    }

    public function testMarkOrderAsPaidMovesReservedTicketsToSold(): void
    {
        [$order, $ticketType] = $this->createPendingOrder(
            quantity: 3,
            soldQuantity: 2,
        );

        $service = $this->makeOrderService();

        $service->markOrderAsPaid($order);

        $order->refresh();
        $ticketType->refresh();

        $this->assertDatabaseHas("orders", [
            "id" => $order->id,
            "status" => OrderStatus::PAID->value,
        ]);

        $this->assertEquals(
            0,
            $ticketType->reserved_quantity
        );

        $this->assertEquals(
            5,
            $ticketType->sold_quantity
        );
    }

    public function testMarkOrderAsPaidIsIdempotent(): void
    {
        [$order, $ticketType] = $this->createPendingOrder(
            quantity: 3,
            soldQuantity: 2,
        );

        $service = $this->makeOrderService();

        $service->markOrderAsPaid($order);

        // Имитируем повторный PAID webhook
        $service->markOrderAsPaid($order);

        $ticketType->refresh();

        $this->assertEquals(
            0,
            $ticketType->reserved_quantity
        );

        // Было 2, оплачено 3. Повторный вызов не должен сделать 8.
        $this->assertEquals(
            5,
            $ticketType->sold_quantity
        );

        $this->assertDatabaseHas("orders", [
            "id" => $order->id,
            "status" => OrderStatus::PAID->value,
        ]);
    }

    public function testCancelOrderReleasesReservation(): void
    {
        [$order, $ticketType] = $this->createPendingOrder(
            quantity: 3,
            soldQuantity: 2,
        );

        $service = $this->makeOrderService();

        $service->cancelOrder($order);

        $ticketType->refresh();

        $this->assertDatabaseHas("orders", [
            "id" => $order->id,
            "status" => OrderStatus::CANCELLED->value,
        ]);

        $this->assertEquals(
            0,
            $ticketType->reserved_quantity
        );

        // При failed-платеже билеты не проданы.
        $this->assertEquals(
            2,
            $ticketType->sold_quantity
        );
    }

    public function testCancelOrderIsIdempotent(): void
    {
        [$order, $ticketType] = $this->createPendingOrder(
            quantity: 3,
            soldQuantity: 2,
        );

        $service = $this->makeOrderService();

        $service->cancelOrder($order);

         // Повторный FAILED webhook.
        $service->cancelOrder($order);

        $ticketType->refresh();

        // reserved_quantity не должен уйти в -3.
        $this->assertEquals(
            0,
            $ticketType->reserved_quantity
        );

        $this->assertEquals(
            2,
            $ticketType->sold_quantity
        );

        $this->assertDatabaseHas("orders", [
            "id" => $order->id,
            "status" => OrderStatus::CANCELLED->value,
        ]);
    }

    public function testCannotMarkCancelledOrderAsPaid(): void
    {
        [$order] = $this->createPendingOrder();

        $service = $this->makeOrderService();

        $service->cancelOrder($order);

        $this->expectException(
            ValidationException::class
        );

        $service->markOrderAsPaid($order);
    }

    public function testCannotCancelPaidOrder(): void
    {
        [$order] = $this->createPendingOrder();

        $service = $this->makeOrderService();

        $service->markOrderAsPaid($order);

        $this->expectException(
            ValidationException::class
        );

        $service->cancelOrder($order);
    }

    public function testMarkOrderAsPaidDispatchesOrderPaidEvent(): void
    {
        [$order] = $this->createPendingOrder(
            quantity: 3,
            soldQuantity: 2,
        );

        EventFacade::fake([
            OrderPaid::class,
        ]);

        $service = $this->makeOrderService();

        $service->markOrderAsPaid($order);

        EventFacade::assertDispatched(
            OrderPaid::class,
            function (OrderPaid $event) use ($order) {
                return $event->order->id === $order->id
                    && $event->order->status === OrderStatus::PAID;
            }
        );
    }

    public function testOrderPaidEventIsDispatchedOnlyOnce(): void
    {
        // тест на идемпотентное создание билетов в ответ на несколько событий OrderPaid
        [$order] = $this->createPendingOrder(
            quantity: 3,
            soldQuantity: 2,
        );

        EventFacade::fake([
            OrderPaid::class,
        ]);

        $service = $this->makeOrderService();

        // Первый успешный переход: PENDING → PAID
        $service->markOrderAsPaid($order);


        // Имитация повторного webhook. Order уже PAID.
        $service->markOrderAsPaid($order);

        EventFacade::assertDispatchedTimes(
            OrderPaid::class,
            1
        );
    }

    public function testCancelledOrderDoesNotDispatchOrderPaidEvent(): void
    {
        // отмененный заказ не вызовет событие OrderPaid и создание билетов
        [$order] = $this->createPendingOrder();

        EventFacade::fake([
            OrderPaid::class,
        ]);

        $service = $this->makeOrderService();

        $service->cancelOrder($order);

        EventFacade::assertNotDispatched(
            OrderPaid::class
        );
    }
}
