<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class EventTest extends TestCase
{
    use RefreshDatabase, WithAuditLogs;
    /**
     * A basic feature test example.
     */
    public function testEventSuccessfulCreation(): void
    {
        $user = User::factory()->create();
        $startedAt = now()->addDay()->toDateTimeString();
        $endedAt = now()->addDays(2)->toDateTimeString();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        $eventInfo = [
            "name" => "Test Event",
            "description" => "Test Description",
            "started_at" => $startedAt,
            "ended_at" => $endedAt,
            "category_id" => $category->id,
            "venue_id" => $venue->id,
            "status" => "draft",
        ];
        $response = $this->actingAs($user)->post("/api/events", $eventInfo);

        $response->assertStatus(201);
        $this->assertDatabaseHas("events", [
            "organizer_id" => $user->id,
            "name" => "Test Event",
            "description" => "Test Description",
            "category_id" => $category->id,
            "venue_id" => $venue->id,
            "status" => EventStatus::DRAFT->value,
        ]);

        $event = Event::query()
            ->where("organizer_id", $user->id)
            ->where("name", "Test Event")
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(EventStatus::DRAFT, $event->status);
        $this->assertLog("event_created", user_id: $user->id, event_id: $event->id);
    }

    public function testClientCannotSetEventStatusOnCreation(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        $response = $this->actingAs($user)->post("/api/events", [
            "name" => "Test Event",
            "description" => "Test Description",
            "started_at" => now()->addDay()->toDateTimeString(),
            "ended_at" => now()->addDays(2)->toDateTimeString(),
            "category_id" => $category->id,
            "venue_id" => $venue->id,

            // Клиент пытается сам опубликовать Event.
            "status" => "published",
        ]);

        $response->assertCreated();

        $event = Event::query()
            ->where("organizer_id", $user->id)
            ->where("name", "Test Event")
            ->firstOrFail();

        // Backend должен проигнорировать status из HTTP-запроса.
        $this->assertSame(EventStatus::DRAFT, $event->status);
    }

    public function testInvalidEventCreation(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post("/api/events", [
            "name" => "",
            "description" => "",
            "started_at" => now()->subDay()->toDateTimeString(),
            "ended_at" => now()->subDays(2)->toDateTimeString(),
            "category_id" => 999999,
            "venue_id" => 999999,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["name", "description", "venue_id", "category_id", "started_at", "ended_at"]);
    }

    public function testEventList(): void
    {
        $user = User::factory()->create();
        $events = Event::factory()->count(3)->state(
            new Sequence(
                ["organizer_id" => $user->id, "created_at" => now()],
                ["organizer_id" => $user->id, "created_at" => now()->subMinute()],
                ["organizer_id" => $user->id, "created_at" => now()->subMinutes(3)]
            )
        )->create();

        $otherUser = User::factory()->create();
        Event::factory()->state(["organizer_id" => $otherUser->id])->create();

        $response = $this->actingAs($user)->get("/api/events");
        $response->assertOk();

        $data = $events->map(function (Event $event) {
            $eventData = $event->toArray();
            return $eventData;
        })->toArray();
        $response->assertJson(["data" => $data]);
        $response->assertJsonCount(3, "data");
    }

    public function testUpdateEventWithoutStatusChange(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        $newCategory = Category::factory()->create();
        $newVenue = Venue::factory()->create();

        $event = Event::factory()->state([
            "organizer_id" => $user->id,
            "category_id" => $category->id,
            "venue_id" => $venue->id,
            "status" => EventStatus::PUBLISHED
        ])->create();

        $newData = [
            "name" => "Updated Event",
            "description" => "Updated Description",
            "started_at" => now()->addDay()->toDateTimeString(),
            "ended_at" => now()->addDays(2)->toDateTimeString(),
            "venue_id" => $newVenue->id,
            "category_id" => $newCategory->id,
        ];

        $response = $this->actingAs($user)->put("/api/events/{$event->id}", $newData);
        $response->assertOk();
        $event->refresh();

        $this->assertSame(
            EventStatus::PUBLISHED,
            $event->status
        );

        $this->assertEquals("Updated Event", $event->name);
        $this->assertEquals("Updated Description", $event->description);
        $this->assertEquals($newCategory->id, $event->category_id);
        $this->assertEquals($newVenue->id, $event->venue_id);

        $this->assertSame(
            EventStatus::PUBLISHED,
            $event->status
        );

        $this->assertLog(
            "event_updated",
            user_id: $user->id,
            event_id: $event->id,
            parameters: $newData,
        );

        $otherUser = User::factory()->create();
        $otherEvent = Event::factory()->state(["organizer_id" => $otherUser->id])->create();
        $response = $this->actingAs($user)->put("/api/events/{$otherEvent->id}", $newData);
        $response->assertForbidden();
    }

    public function testPublishDraftEvent(): void
    {
        $user = User::factory()->create();

        $event = Event::factory()->create([
            "organizer_id" => $user->id,
            "status" => EventStatus::DRAFT,
        ]);

        $response = $this->actingAs($user)
            ->post("/api/events/{$event->id}/publish");

        $response->assertOk();

        $event->refresh();

        $this->assertSame(
            EventStatus::PUBLISHED,
            $event->status
        );

        $this->assertLog(
            "event_published",
            user_id: $user->id,
            event_id: $event->id,
        );
    }

    public function testForbidDoublePublish(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            "organizer_id" => $user->id,
            "status" => EventStatus::PUBLISHED,
        ]);

        $response = $this->actingAs($user)
            ->post("/api/events/{$event->id}/publish");

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            "status",
        ]);

        $event->refresh();

        $this->assertSame(
            EventStatus::PUBLISHED,
            $event->status
        );
    }

    public function testPublishedEventCanBeCancelled(): void
    {
        $user = User::factory()->create();

        $event = Event::factory()->create([
            "organizer_id" => $user->id,
            "status" => EventStatus::PUBLISHED,
        ]);

        $response = $this->actingAs($user)
            ->post("/api/events/{$event->id}/cancel");

        $response->assertOk();

        $event->refresh();

        $this->assertSame(
            EventStatus::CANCELLED,
            $event->status
        );

        $this->assertLog(
            "event_cancelled",
            user_id: $user->id,
            event_id: $event->id,
        );
    }

    public function testDraftEventCannotBeCancelled(): void
    {
        $user = User::factory()->create();

        $event = Event::factory()->create([
            "organizer_id" => $user->id,
            "status" => EventStatus::DRAFT,
        ]);

        $response = $this->actingAs($user)
            ->post("/api/events/{$event->id}/cancel");

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            "status",
        ]);

        $event->refresh();

        $this->assertSame(
            EventStatus::DRAFT,
            $event->status
        );
    }

    public function testDeleteEvent(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->state(["organizer_id" => $user->id])->create();

        $response = $this->actingAs($user)->delete("/api/events/{$event->id}");
        $response->assertOk();
        $this->assertSoftDeleted("events", ["id" => $event->id]);

        $this->assertLog("event_deleted", user_id: $user->id, event_id: $event->id);

        $otherUser = User::factory()->create();
        $otherEvent = Event::factory()->state(["organizer_id" => $otherUser->id])->create();
        $response = $this->actingAs($user)->delete("/api/events/{$otherEvent->id}");
        $response->assertForbidden();
    }
}
