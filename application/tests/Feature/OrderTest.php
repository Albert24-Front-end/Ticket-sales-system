<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventStatus;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\TicketType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class OrderTest extends TestCase
{
    use RefreshDatabase, WithFaker, WithAuditLogs;
    private User $user;
    private Event $event;
    private TicketType $ticketType;
//    private ?CarbonImmutable $now = null;

//успешное создание заказа; +
//нельзя покупать билет на draft/cancelled/finished Event; +
//нельзя указать TicketType другого Event;
//нельзя запросить quantity <= 0;
//нельзя приобрести больше доступного остатка; +
//несколько TicketType корректно формируют OrderItem;
//сумма заказа рассчитывается backend'ом;
//после создания Order его статус pending;
//после создания Order увеличивается reserved_quantity;
//не создаётся Order и не меняются остатки, если одна из позиций невалидна.

    public function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create(["organizer_id" => $this->user->id, "status" => EventStatus::PUBLISHED]);
//        $this->now = CarbonImmutable::now();
    }

    public function testOrderSuccessfulCreation(): void
    {
        $this->event->update(["status" => EventStatus::PUBLISHED]);
        $standardTicket = TicketType::factory()->create([
            "event_id" => $this->event->id,
            "price" => 100_000,
            "quantity" => 100,
            "sold_quantity" => 0,
            "reserved_quantity" => 0,
        ]);

        $vipTicket = TicketType::factory()->create([
            "event_id" => $this->event->id,
            "price" => 250_000,
            "quantity" => 50,
            "sold_quantity" => 0,
            "reserved_quantity" => 0,
        ]);

        $orderInfo = [
            "items" => [
                [
                    "ticket_type_id" => $standardTicket->id,
                    "ticket_quantity" => 2,
                ],
                [
                    "ticket_type_id" => $vipTicket->id,
                    "ticket_quantity" => 1,
                ]

            ]
        ];

        $response = $this->actingAs($this->user)->post("/api/events/{$this->event->id}/orders", $orderInfo);
        $response->assertStatus(201);

        $order = Order::query()->firstOrFail();
        $this->assertDatabaseHas('orders', [
            "id" => $order->id,
            "user_id" => $this->user->id,
            "event_id" => $this->event->id,
            "total_amount" => 450_000,
            "status" => OrderStatus::PENDING,
        ]);

        $this->assertDatabaseHas("order_items", [
            "order_id" => $order->id,
            "ticket_type_id" => $standardTicket->id,
            "quantity" => 2,
            "unit_price" => 100_000,
        ]);

        $this->assertDatabaseHas("order_items", [
            "order_id" => $order->id,
            "ticket_type_id" => $vipTicket->id,
            "quantity" => 1,
            "unit_price" => 250_000,
        ]);

        $standardTicket->refresh();
        $vipTicket->refresh();

        $this->assertEquals(2, $standardTicket->reserved_quantity);
        $this->assertEquals(1, $vipTicket->reserved_quantity);

        $this->assertLog("order_created", user_id: $this->user->id, event_id: $this->event->id);
    }

    public static function unavailableEventStatuses(): array
    {
        return [
            [EventStatus::DRAFT],
            [EventStatus::CANCELLED],
            [EventStatus::FINISHED],
        ];
    }
    #[DataProvider('unavailableEventStatuses')]
    public function testCannotBuyTicketForUnpublishedEvent(EventStatus $status): void
    {
        $event = Event::factory()->create(["status" => $status]);
        $standardTicket = TicketType::factory()->create([
            "event_id" => $event->id,
            "price" => 100_000,
            "quantity" => 100,
            "sold_quantity" => 0,
        ]);

        $vipTicket = TicketType::factory()->create([
            "event_id" => $this->event->id,
            "price" => 250_000,
            "quantity" => 50,
            "sold_quantity" => 0,
        ]);
        $response = $this->actingAs($this->user)->post("/api/events/{$event->id}/orders", [
            "items" => [
                [
                    "ticket_type_id" => $standardTicket->id,
                    "ticket_quantity" => 2,
                ],
                [
                    "ticket_type_id" => $vipTicket->id,
                    "ticket_quantity" => 1,
                ]

            ]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            "event",
        ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertEquals(0, $standardTicket->fresh()->reserved_quantity);
    }

    public function testTicketsInsufficientQuantity(): void
    {
        $standardTicket = TicketType::factory()->create([
            "event_id" => $this->event->id,
            "price" => 100_000,
            "quantity" => 10,
            "sold_quantity" => 10,
            "reserved_quantity" => 0,
        ]);

        $response = $this->actingAs($this->user)->post("/api/events/{$this->event->id}/orders", [
            "items" => [
                [
                    "ticket_type_id" => $standardTicket->id,
                    "ticket_quantity" => 1,
                ]
            ]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            "items.0.ticket_quantity",
        ]);
    }

    public function testCannotOrderTicketTypeFromAnotherEvent(): void
    {
        $otherEvent = Event::factory()->create();
        $otherTicketType = TicketType::factory()->create(["event_id" => $otherEvent->id]);

        $response = $this->actingAs($this->user)->post("/api/events/{$this->event->id}/orders", [
            "items" => [
                [
                    "ticket_type_id" => $otherTicketType->id,
                    "ticket_quantity" => 1,
                ]
            ]
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            "items.0.ticket_type_id",
        ]);
    }

    public function testTicketsInvalidQuantityInOrderItem(): void
    {
        $standardTicket = TicketType::factory()->create([
            "event_id" => $this->event->id,
            "price" => 100_000,
            "quantity" => 50,
            "sold_quantity" => 0,
            "reserved_quantity" => 0,
        ]);

        $response = $this->actingAs($this->user)->post("/api/events/{$this->event->id}/orders", [
            "items" => [
                [
                    "ticket_type_id" => $standardTicket->id,
                    "ticket_quantity" => 0,
                ]
            ]
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["items.0.ticket_quantity"]);

        $response = $this->actingAs($this->user)->post("/api/events/{$this->event->id}/orders", [
            "items" => [
                [
                    "ticket_type_id" => $standardTicket->id,
                    "ticket_quantity" => -50,
                ]
            ]
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["items.0.ticket_quantity"]);
    }


    public function testIncreasingReservedQuantityAfterOrderCreation(): void
    {
        $standardTicket = TicketType::factory()->create([
            "event_id" => $this->event->id,
            "price" => 100_000,
            "quantity" => 100,
            "sold_quantity" => 0,
            "reserved_quantity" => 0,
        ]);

        $response = $this->actingAs($this->user)->post("/api/events/{$this->event->id}/orders", [
            "items" => [
                [
                    "ticket_type_id" => $standardTicket->id,
                    "ticket_quantity" => 10,
                ]
            ]
        ]);
        $response->assertCreated();
        $standardTicket->refresh();

        $this->assertEquals(100, $standardTicket->quantity);
        $this->assertEquals(0, $standardTicket->sold_quantity);
        $this->assertEquals(10, $standardTicket->reserved_quantity);
        $this->assertEquals(90, $standardTicket->available_quantity);

        $order = Order::where("user_id", $this->user->id)->where("event_id", $this->event->id)->firstOrFail();

        $this->assertDatabaseHas("order_items", [
            "order_id" => $order->id,
            "ticket_type_id" => $standardTicket->id,
            "quantity" => 10,
            "unit_price" => 100_000,
        ]);

        $this->assertDatabaseHas("orders", [
            "id" => $order->id,
            "event_id" => $this->event->id,
            "user_id" => $this->user->id,
            "total_amount" => 1_000_000,
            "status" => OrderStatus::PENDING,
        ]);
    }

    public function testCannotBuyReservedTickets(): void
    {
        $standardTicket = TicketType::factory()->create([
            "event_id" => $this->event->id,
            "price" => 100_000,
            "quantity" => 100,
            "sold_quantity" => 0,
            "reserved_quantity" => 100,
        ]);

        $response = $this->actingAs($this->user)->post("/api/events/{$this->event->id}/orders", [
            "items" => [
                [
                    "ticket_type_id" => $standardTicket->id,
                    "ticket_quantity" => 10,
                ]
            ]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["items.0.ticket_quantity"]);
    }

    public function testInvalidOrderItemOrderFailedCreation(): void
    {
        $standardTicket = TicketType::factory()->create([
            "event_id" => $this->event->id,
            "price" => 100_000,
            "quantity" => 100,
            "sold_quantity" => 0,
            "reserved_quantity" => 50,
        ]);

        $vipTicket = TicketType::factory()->create([
            "event_id" => $this->event->id,
            "price" => 250_000,
            "quantity" => 50,
            "sold_quantity" => 45,
            "reserved_quantity" => 0,
        ]);

        $response = $this->actingAs($this->user)->post("/api/events/{$this->event->id}/orders", [
            "items" => [
                [
                    "ticket_type_id" => $standardTicket->id,
                    "ticket_quantity" => 10,
                ],
                [
                    "ticket_type_id" => $vipTicket->id,
                    "ticket_quantity" => 10,
                ]
            ]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["items.1.ticket_quantity"]);

        $this->assertDatabaseCount("orders", 0);
        $this->assertDatabaseCount("order_items", 0);

        $standardTicket->refresh();
        $vipTicket->refresh();

        $this->assertEquals(50, $standardTicket->reserved_quantity);
        $this->assertEquals(0, $vipTicket->reserved_quantity);
    }
}
