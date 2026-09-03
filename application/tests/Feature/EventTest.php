<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventStatus;
use App\Models\User;
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
        $startedAt = now()->addDay();
        $endedAt = now()->addDays(2);
        $eventInfo = [
            "name" => "Test Events",
            "description" => "Test Description",
            "started_at" => $startedAt,
            "ended_at" => $endedAt,
            "location" => "Test Location",
            "organizer" => "Test Organizer",
            "category" => "Test Category",
            "status" => "draft",
        ];
        $response = $this->actingAs($user)->post("/api/events", $eventInfo);

        $response->assertStatus(201);
        $this->assertDatabaseHas("events", [
            "name" => "Test Events",
        ]);

        $event = Event::query()
            ->where("user_id", $user->id)
            ->where("name", "Test Events")
            ->where("description", "Test Description")
            ->where("started_at",$startedAt->format("Y-m-d H:i:s"))
            ->where("ended_at", $endedAt->format("Y-m-d H:i:s"))
            ->where("location", "Test Location")
            ->where("organizer", "Test Organizer")
            ->where("category", "Test Category")
            ->where("status",  EventStatus::DRAFT->value)
            ->first();

        $this->assertNotNull($event);
        $this->assertLog("event_created", user_id: $user->id, event_id: $event->id);
    }

    public function testInvalidEventCreation(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post("/api/events", [
            "name" => "",
            "description" => "",
            "started_at" => now()->addDay(),
            "ended_at" => now()->addDays(2),
            "location" => "",
            "organizer" => "",
            "category" => "",
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["name", "description", "location", "organizer", "category"]);
    }

    public function testEventList(): void
    {
        $user = User::factory()->create();
        $events = Event::factory()->count(3)->state(
            new Sequence(
                ["user_id" => $user->id, "created_at" => now()],
                ["user_id" => $user->id, "created_at" => now()->subMinute()],
                ["user_id" => $user->id, "created_at" => now()->subMinutes(3)]
            )
        )->create();

        $otherUser = User::factory()->create();
        Event::factory()->state(["user_id" => $otherUser->id])->create();

        $response = $this->actingAs($user)->get("/api/events");
        $response->assertOk();

        $data = $events->map(function (Event $event) {
            $eventData = $event->toArray();
            return $eventData;
        })->toArray();
        $response->assertJson(["data" => $data]);
    }

    public function testUpdateEvent(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->state(["user_id" => $user->id])->create();

        $newData = [
            "name" => "Updated Event",
            "description" => "Updated Description",
            "started_at" => now()->addDay()->toDateTimeString(),
            "ended_at" => now()->addDays(2)->toDateTimeString(),
            "location" => "Updated Location",
            "organizer" => "Updated Organizer",
            "category" => "Updated Category",
            "status" => "draft",
        ];

        $response = $this->actingAs($user)->put("/api/events/{$event->id}", $newData);
        $response->assertOk();
        $event->refresh();

        $this->assertEquals("Updated Event", $event->name);
        $this->assertEquals("Updated Description", $event->description);
        $this->assertEquals("Updated Location", $event->location);
        $this->assertEquals("Updated Organizer", $event->organizer);
        $this->assertEquals("Updated Category", $event->category);
        $this->assertSame(EventStatus::DRAFT, $event->status);

        $expectedLogParameters = $newData;
        $expectedLogParameters['status'] = EventStatus::DRAFT;

        $this->assertLog(
            "event_updated",
            user_id: $user->id,
            event_id: $event->id,
            parameters: $expectedLogParameters,
        );

        $otherUser = User::factory()->create();
        $otherEvent = Event::factory()->state(["user_id" => $otherUser->id])->create();
        $response = $this->actingAs($user)->put("/api/events/{$otherEvent->id}", $newData);
        $response->assertForbidden();
    }

    public function testDeleteEvent(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->state(["user_id" => $user->id])->create();

        $response = $this->actingAs($user)->delete("/api/events/{$event->id}");
        $response->assertOk();
        $this->assertSoftDeleted("events", ["id" => $event->id]);

        $this->assertLog("event_deleted", user_id: $user->id, event_id: $event->id);

        $otherUser = User::factory()->create();
        $otherEvent = Event::factory()->state(["user_id" => $otherUser->id])->create();
        $response = $this->actingAs($user)->delete("/api/events/{$otherEvent->id}");
        $response->assertForbidden();
    }
}
