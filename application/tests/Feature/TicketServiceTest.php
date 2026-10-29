<?php

namespace Tests\Feature;

use App\Models\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\TicketType;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createPaidOrder(): array
    {
        $user = User::factory()->create();

        $event = Event::factory()->create([
            "organizer_id" => $user->id,
        ]);

        $standard = TicketType::factory()->create([
            "event_id" => $event->id,
            "name" => "Standard",
            "price" => 100_000,
            "quantity" => 100,
        ]);

        $vip = TicketType::factory()->create([
            "event_id" => $event->id,
            "name" => "VIP",
            "price" => 250_000,
            "quantity" => 50,
        ]);

        $order = Order::create([
            "user_id" => $user->id,
            "event_id" => $event->id,
            "total_amount" => 450_000,
            "status" => OrderStatus::PAID,
        ]);


        $order->orderItems()->create([
            "ticket_type_id" => $standard->id,
            "quantity" => 2,
            "unit_price" => 100_000,
        ]);

        $order->orderItems()->create([
            "ticket_type_id" => $vip->id,
            "quantity" => 1,
            "unit_price" => 250_000,
        ]);

        return [
            $order,
            $user,
            $event,
            $standard,
            $vip,
        ];
    }

    public function testGeneratesOneTicketForEachPurchasedUnit(): void
    {
        [
            $order,
            $user,
            $event,
            $standard,
            $vip,
        ] = $this->createPaidOrder();

        $service = app(
            TicketService::class
        );

        $tickets = $service->generateTickets(
            $order
        );

        $this->assertCount(
            3,
            $tickets
        );

        $this->assertDatabaseCount(
            "tickets",
            3
        );

        $this->assertDatabaseHas("tickets", [
            "order_id" => $order->id,
            "user_id" => $user->id,
            "event_id" => $event->id,
            "ticket_type_id" => $standard->id,
            "ticket_index" => 1,
            "status" =>
                TicketStatus::ACTIVE->value,
            "refund_required" => false,
        ]);

        $this->assertDatabaseHas("tickets", [
            "order_id" => $order->id,
            "ticket_type_id" => $standard->id,
            "ticket_index" => 2,
        ]);

        $this->assertDatabaseHas("tickets", [
            "order_id" => $order->id,
            "ticket_type_id" => $vip->id,
            "ticket_index" => 1,
        ]);
    }

    public function testGeneratedTicketNumbersAreUnique(): void
    {
        [$order] = $this->createPaidOrder();

        $tickets = app(
            TicketService::class
        )->generateTickets($order);

        $numbers = $tickets->pluck(
            "number"
        );

        $this->assertCount(
            3,
            $numbers
        );

        $this->assertCount(
            3,
            $numbers->unique()
        );

        foreach ($numbers as $number) {
            $this->assertStringStartsWith(
                "EVT-",
                $number
            );
        }
    }

    public function testTicketGenerationIsIdempotent(): void
    {
        [$order] = $this->createPaidOrder();

        $service = app(
            TicketService::class
        );

        // первое выполнение queued listener.

        $service->generateTickets($order);

        // Имитация retry этой же job.
        $service->generateTickets($order);

        // Всё ещё только: Standard × 2VIP × 1
        $this->assertDatabaseCount(
            "tickets",
            3
        );
    }

    public function testCannotGenerateTicketsForPendingOrder(): void
    {
        [$order] = $this->createPaidOrder();

        $order->update([
            "status" => OrderStatus::PENDING,
        ]);

        $service = app(
            TicketService::class
        );

        try {
            $service->generateTickets($order);

            $this->fail(
                "ValidationException was expected."
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                "order",
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            "tickets",
            0
        );
    }

    public function testCannotGenerateTicketsForPaidOrderWithoutItems(): void
    {
        $user = User::factory()->create();

        $event = Event::factory()->create([
            "organizer_id" => $user->id,
        ]);

        $order = Order::create([
            "user_id" => $user->id,
            "event_id" => $event->id,
            "total_amount" => 0,
            "status" => OrderStatus::PAID,
        ]);

        $this->expectException(
            ValidationException::class
        );

        app(TicketService::class)->generateTickets($order);
    }
}
