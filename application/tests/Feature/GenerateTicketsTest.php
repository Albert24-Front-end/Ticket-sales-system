<?php

namespace Tests\Feature;

use App\Events\OrderPaid;
use App\Listeners\GenerateTickets;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\TicketType;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateTicketsTest extends TestCase
{
    use RefreshDatabase;

    private function createPaidOrder(): Order
    {
        $user = User::factory()->create();

        $event = Event::factory()->create([
            "organizer_id" => $user->id,
        ]);

        $ticketType = TicketType::factory()->create([
            "event_id" => $event->id,
            "price" => 100_000,
            "quantity" => 100,
        ]);

        $order = Order::create([
            "user_id" => $user->id,
            "event_id" => $event->id,
            "total_amount" => 200_000,
            "status" => OrderStatus::PAID,
        ]);

        $order->orderItems()->create([
            "ticket_type_id" => $ticketType->id,
            "quantity" => 2,
            "unit_price" => 100_000,
        ]);

        return $order;
    }

    public function testGenerateTicketsListenerUsesQueue(): void
    {
        $listener = app(
            GenerateTickets::class
        );

        $this->assertInstanceOf(
            ShouldQueue::class,
            $listener
        );
    }

    public function testListenerGeneratesTicketsForPaidOrder(): void
    {
        $order = $this->createPaidOrder();

        $listener = new GenerateTickets(
            app(TicketService::class)
        );

        $listener->handle(
            new OrderPaid($order)
        );

        $this->assertDatabaseCount(
            "tickets",
            2
        );

        $this->assertDatabaseHas("tickets", [
            "order_id" => $order->id,
            "ticket_index" => 1,
        ]);

        $this->assertDatabaseHas("tickets", [
            "order_id" => $order->id,
            "ticket_index" => 2,
        ]);
    }

    public function testRepeatedListenerExecutionDoesNotDuplicateTickets(): void
    {
        $order = $this->createPaidOrder();

        $listener = new GenerateTickets(
            app(TicketService::class)
        );

        $event = new OrderPaid($order);

        $listener->handle($event);


        // имитация queue retry.
        $listener->handle($event);

        $this->assertDatabaseCount(
            "tickets",
            2
        );
    }
}
