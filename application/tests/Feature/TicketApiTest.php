<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    private function createTicketContext(
        User $owner
    ): array {
        $organizer = User::factory()->create();

        $event = Event::factory()->create([
            "organizer_id" => $organizer->id,
        ]);

        $ticketType = TicketType::factory()->create([
            "event_id" => $event->id,
            "name" => "VIP",
            "price" => 250_000,
            "quantity" => 100,
            "sold_quantity" => 1,
            "reserved_quantity" => 0,
        ]);

        $order = Order::create([
            "user_id" => $owner->id,
            "event_id" => $event->id,
            "total_amount" => 250_000,
            "status" => "paid",
        ]);

        return [
            $event,
            $ticketType,
            $order,
        ];
    }

    private function createTicket(
        User $owner,
        Event $event,
        TicketType $ticketType,
        Order $order,
        int $ticketIndex,
        string $number,
    ): Ticket {
        return Ticket::create([
            "user_id" => $owner->id,
            "event_id" => $event->id,
            "ticket_type_id" => $ticketType->id,
            "order_id" => $order->id,

            "ticket_index" => $ticketIndex,

            "number" => $number,

            "status" => "active",

            "refund_required" => false,
        ]);
    }

    public function testUnauthenticatedUserCannotGetTickets(): void
    {
        $response = $this->getJson(
            "/api/tickets"
        );

        $response->assertUnauthorized();
    }

    public function testUserGetsOnlyOwnTickets(): void
    {
        $user = User::factory()->create();

        $otherUser = User::factory()->create();

        [
            $event,
            $ticketType,
            $order,
        ] = $this->createTicketContext($user);

        $firstTicket = $this->createTicket(
            $user,
            $event,
            $ticketType,
            $order,
            1,
            "EVT-OWN-001"
        );

        $secondTicket = $this->createTicket(
            $user,
            $event,
            $ticketType,
            $order,
            2,
            "EVT-OWN-002"
        );

        [
            $otherEvent,
            $otherTicketType,
            $otherOrder,
        ] = $this->createTicketContext(
            $otherUser
        );

        $otherTicket = $this->createTicket(
            $otherUser,
            $otherEvent,
            $otherTicketType,
            $otherOrder,
            1,
            "EVT-OTHER-001"
        );

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/tickets"
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                2,
                "data"
            )
            ->assertJsonPath(
                "meta.total",
                2
            );

        $numbers = collect(
            $response->json("data")
        )->pluck("number");

        $this->assertTrue(
            $numbers->contains(
                $firstTicket->number
            )
        );

        $this->assertTrue(
            $numbers->contains(
                $secondTicket->number
            )
        );

        $this->assertFalse(
            $numbers->contains(
                $otherTicket->number
            )
        );
    }

    public function testTicketListIsPaginated(): void
    {
        $user = User::factory()->create();

        [
            $event,
            $ticketType,
            $order,
        ] = $this->createTicketContext(
            $user
        );

        // создаем 21 билет, поэтому ожидаем 2 страницы
        for ($index = 1; $index <= 21; $index++) {
            $this->createTicket(
                $user,
                $event,
                $ticketType,
                $order,
                $index,
                sprintf(
                    "EVT-PAGE-%03d",
                    $index
                )
            );
        }

        Sanctum::actingAs($user);

        $firstPage = $this->getJson(
            "/api/tickets"
        );

        $firstPage
            ->assertOk()
            ->assertJsonCount(
                20,
                "data"
            )
            ->assertJsonPath(
                "meta.current_page",
                1
            )
            ->assertJsonPath(
                "meta.per_page",
                20
            )
            ->assertJsonPath(
                "meta.total",
                21
            )
            ->assertJsonPath(
                "meta.last_page",
                2
            );

        $secondPage = $this->getJson(
            "/api/tickets?page=2"
        );

        $secondPage
            ->assertOk()
            ->assertJsonCount(
                1,
                "data"
            )
            ->assertJsonPath(
                "meta.current_page",
                2
            )
            ->assertJsonPath(
                "meta.total",
                21
            );
    }

    public function testUserCanGetOwnTicket(): void
    {
        $user = User::factory()->create();

        [
            $event,
            $ticketType,
            $order,
        ] = $this->createTicketContext(
            $user
        );

        $ticket = $this->createTicket(
            $user,
            $event,
            $ticketType,
            $order,
            1,
            "EVT-MY-TICKET"
        );

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/tickets/{$ticket->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                "data.id",
                $ticket->id
            )
            ->assertJsonPath(
                "data.number",
                "EVT-MY-TICKET"
            )
            ->assertJsonPath(
                "data.status",
                "active"
            )
            ->assertJsonPath(
                "data.refund_required",
                false
            )
            ->assertJsonPath(
                "data.event_id",
                $event->id
            )
            ->assertJsonPath(
                "data.ticket_type.id",
                $ticketType->id
            )
            ->assertJsonPath(
                "data.ticket_type.name",
                "VIP"
            )
            ->assertJsonPath(
                "data.ticket_type.price",
                250_000
            )
            ->assertJsonPath(
                "data.order.id",
                $order->id
            )
            ->assertJsonPath(
                "data.order.status",
                "paid"
            );
    }

    public function testUserCannotGetAnotherUsersTicket(): void
    {
        $owner = User::factory()->create();

        $anotherUser = User::factory()->create();

        [
            $event,
            $ticketType,
            $order,
        ] = $this->createTicketContext(
            $owner
        );

        $ticket = $this->createTicket(
            $owner,
            $event,
            $ticketType,
            $order,
            1,
            "EVT-PRIVATE"
        );

        Sanctum::actingAs(
            $anotherUser
        );

        $response = $this->getJson(
            "/api/tickets/{$ticket->id}"
        );

        $response->assertForbidden();
    }

    public function testUnknownTicketReturns404(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/tickets/999999"
        );

        $response->assertNotFound();
    }
}
