<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class TicketTypeTest extends TestCase
{
    use WithAuditLogs, RefreshDatabase, WithFaker;
    public function testTicketTypeSuccessfulCreation(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(["organizer_id" => $user->id]);

        $ticketTypeInfo = [
            "name" => "Test Ticket Type",
            "description" => "Test Description",
            "price" => 1000,
            "quantity" => 10,
        ];

        $response = $this->actingAs($user)->post("/api/events/{$event->id}/ticket-types", $ticketTypeInfo);
        $response->assertStatus(201);
        $this->assertDatabaseHas("ticket_types", [
            "event_id" => $event->id,
            "name" => "Test Ticket Type",
            "price" => 1000,
            "quantity" => 10,
            "sold_quantity" => 0
        ]);

        $this->assertLog("ticket_type_created", user_id: $user->id, event_id: $event->id);
    }

    public function testClientCannotSetSoldQuantity(): void
    {
        $user = User::factory()->create();

        $event = Event::factory()->create([
            "organizer_id" => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->post("/api/events/{$event->id}/ticket-types", [
                "name" => "VIP",
                "description" => "VIP Ticket",
                "price" => 250000,
                "quantity" => 50,
                "sold_quantity" => 40,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas("ticket_types", [
            "event_id" => $event->id,
            "name" => "VIP",
            "quantity" => 50,
            "sold_quantity" => 0,
        ]);
    }

    public function testCannotCreateTicketTypeForForeignEvent(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $event = Event::factory()->create([
            "organizer_id" => $otherUser->id,
        ]);

        $response = $this->actingAs($user)
            ->post("/api/events/{$event->id}/ticket-types", [
                "name" => "VIP",
                "description" => "VIP Ticket",
                "price" => 250000,
                "quantity" => 50,
                "sold_quantity" => 0
            ]);

        $response->assertForbidden();
    }

    public function testTicketTypeInvalidCreation(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(["organizer_id" => $user->id]);
        $response = $this->actingAs($user)->post("/api/events/{$event->id}/ticket-types", [
            "name" => "",
            "description" => "",
            "price" => -1,
            "quantity" => 0,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["name", "description", "price", "quantity"]);

//         тест на превышение числа доступных мест над вместимостью площадки мероприятия
//        $response = $this->actingAs($user)->post("/api/events/{$event->id}/ticket-types", [
//            "name" => "somenewname",
//            "description" => "somedescription",
//            "price" => 999999,
//            "quantity" => $event->venue->capacity + 1,
//        ]);
//        $response->assertStatus(422);
//        $response->assertJsonValidationErrors(["quantity"]);
    }

    public function testCannotExceedVenueCapacity(): void
    {
        $user = User::factory()->create();
        $venue = Venue::factory()->create([
            "capacity" => 300,
        ]);
        $event = Event::factory()->create(["organizer_id" => $user->id, "venue_id" => $venue->id]);
        TicketType::factory()->create([
            "event_id" => $event->id,
            "quantity" => 200,
        ]);
        $response = $this->actingAs($user)->post("/api/events/{$event->id}/ticket-types", [
            "name" => "some name",
            "description" => "some description",
            "price" => 999999,
            "quantity" => 101,
            "sold_quantity" => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["quantity"]);
    }

    public function testTicketTypeList(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(["organizer_id" => $user->id]);
        $ticketTypes = TicketType::factory()->count(3)->state(
            new Sequence(
                ["event_id" => $event->id, "created_at" => now()],
                ["event_id" => $event->id, "created_at" => now()->subMinute()],
                ["event_id" => $event->id, "created_at" => now()->subMinutes(3)]
            )
        )->create();

        $otherEvent = Event::factory()->create();
        TicketType::factory()->state(["event_id" => $otherEvent->id])->create();

        $response = $this->actingAs($user)->get("/api/events/{$event->id}/ticket-types");
        $response->assertOk();

        $data = $ticketTypes->map(function (TicketType $ticketType) {
            return $ticketType->toArray();
        })->toArray();
        $response->assertJson(["data" => $data]);
        $response->assertJsonCount(3, "data");
        $response->assertJsonPath(
            "data.0.id",
            $ticketTypes[0]->id
        );

        $response->assertJsonPath(
            "data.1.id",
            $ticketTypes[1]->id
        );

        $response->assertJsonPath(
            "data.2.id",
            $ticketTypes[2]->id
        );
    }

    public function testUpdateTicketType(): void
    {
        $user = User::factory()->create();
        $venue = Venue::factory()->create([
            "capacity" => 500,
        ]);
        $event = Event::factory()->create(["organizer_id" => $user->id, "venue_id" => $venue->id]);
        $ticketType =TicketType::factory()->create(["event_id" => $event->id, "quantity" => 200, "sold_quantity" => 100]);

        $newData = [
            "name" => "Updated Ticket Type",
            "description" => "Updated Description",
            "price" => 10000,
            "quantity" => 300,
        ];

        $response = $this->actingAs($user)->put("/api/events/{$event->id}/ticket-types/{$ticketType->id}", $newData);
        $response->assertOk();
        $ticketType->refresh();

        $this->assertEquals("Updated Ticket Type", $ticketType->name);
        $this->assertEquals("Updated Description", $ticketType->description);
        $this->assertEquals(10000, $ticketType->price);
        $this->assertEquals(300, $ticketType->quantity);
        $this->assertEquals(100, $ticketType->sold_quantity);

        // общее количество билетов не может быть изменено на меньшее, чем проданное количество
        $response = $this->actingAs($user)->put("/api/events/{$event->id}/ticket-types/{$ticketType->id}", [
            "name" => $ticketType->name,
            "description" => $ticketType->description,
            "price" => $ticketType->price,
            "quantity" => 50,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["quantity"]);
        $ticketType->refresh();
        $this->assertEquals(300, $ticketType->quantity);
        $this->assertEquals(100, $ticketType->sold_quantity);

        $this->assertLog("ticket_type_updated", user_id: $user->id, event_id: $event->id, parameters: (array) $newData);

        // тест на запрет изменения типа билетов для другого события
        $otherEvent = Event::factory()->state(["organizer_id" => $user->id])->create();
        $otherTicketType = TicketType::factory()->state(["event_id" => $otherEvent->id])->create();
        $response = $this->actingAs($user)->put("/api/events/{$event->id}/ticket-types/{$otherTicketType->id}", $newData);
        $response->assertStatus(404);

        // тест на запрет изменения типа билетов другого организатора
        $otherUser = User::factory()->create();
        $foreignEvent = Event::factory()->create(["organizer_id" => $otherUser->id]);
        $foreignTicketType = TicketType::factory()->create([
            "event_id" => $foreignEvent->id,
        ]);
        $response = $this->actingAs($user)->put("/api/events/{$foreignEvent->id}/ticket-types/{$foreignTicketType->id}", $newData);
        $response->assertStatus(403);
    }

    public function testUpdateTicketTypeDoesNotCountCurrentQuantityTwice(): void
    {
        $user = User::factory()->create();

        $venue = Venue::factory()->create([
            "capacity" => 300,
        ]);

        $event = Event::factory()->create([
            "organizer_id" => $user->id,
            "venue_id" => $venue->id,
        ]);

        TicketType::factory()->create([
            "event_id" => $event->id,
            "quantity" => 200,
            "sold_quantity" => 0,
        ]);

        $ticketType = TicketType::factory()->create([
            "event_id" => $event->id,
            "name" => "VIP",
            "quantity" => 100,
            "sold_quantity" => 0,
        ]);

        // итого сейчас ровно 300 — вся вместимость venue.
        // уменьшаем VIP со 100 до 90.
        $newData = [
            "name" => "VIP Updated",
            "description" => "Updated Description",
            "price" => 250000,
            "quantity" => 90,
        ];

        $response = $this->actingAs($user)->put(
            "/api/events/{$event->id}/ticket-types/{$ticketType->id}",
            $newData
        );
        $response->assertOk();
        $ticketType->refresh();
        $this->assertEquals(90, $ticketType->quantity);
    }

    public function testUpdateTicketTypeCannotExceedVenueCapacity(): void
    {
        $user = User::factory()->create();

        $venue = Venue::factory()->create([
            "capacity" => 300,
        ]);

        $event = Event::factory()->create([
            "organizer_id" => $user->id,
            "venue_id" => $venue->id,
        ]);

        TicketType::factory()->create([
            "event_id" => $event->id,
            "quantity" => 200,
        ]);

        $ticketType = TicketType::factory()->create([
            "event_id" => $event->id,
            "quantity" => 100,
        ]);

        $response = $this->actingAs($user)->put(
            "/api/events/{$event->id}/ticket-types/{$ticketType->id}",
            [
                "name" => "VIP",
                "description" => "VIP Ticket",
                "price" => 250000,
                "quantity" => 101,
            ]
        );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            "quantity",
        ]);

        $ticketType->refresh();

        // неудачный запрос ничего не изменил.
        $this->assertEquals(100, $ticketType->quantity);
    }

    public function testDeleteTicketType(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(["organizer_id" => $user->id]);
        $ticketType = TicketType::factory()->create(["event_id" => $event->id]);

        $response = $this->actingAs($user)->delete("/api/events/{$event->id}/ticket-types/{$ticketType->id}");
        $response->assertOk();
        $this->assertSoftDeleted("ticket_types", ["id" => $ticketType->id]);

        $this->assertLog("ticket_type_deleted", user_id: $user->id, event_id: $event->id);

        // тест на запрет удаления типа билетов для другого события
        $otherEvent = Event::factory()->state(["organizer_id" => $user->id])->create();
        $otherTicketType = TicketType::factory()->state(["event_id" => $otherEvent->id])->create();
        $response = $this->actingAs($user)->delete("/api/events/{$event->id}/ticket-types/{$otherTicketType->id}");
        $response->assertNotFound();

        // тест на запрет удаления типа билетов другого организатора
        $otherUser = User::factory()->create();
        $foreignEvent = Event::factory()->create(["organizer_id" => $otherUser->id]);
        $foreignTicketType = TicketType::factory()->create([
            "event_id" => $foreignEvent->id,
        ]);
        $response = $this->actingAs($user)->delete("/api/events/{$foreignEvent->id}/ticket-types/{$foreignTicketType->id}");
        $response->assertForbidden();
    }
}
