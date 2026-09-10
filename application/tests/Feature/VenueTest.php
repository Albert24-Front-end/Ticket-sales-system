<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Venue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class VenueTest extends TestCase
{
    use RefreshDatabase, WithAuditLogs, WithFaker;
    /**
     * A basic feature test example.
     */
    public function testSuccessfulVenueCreation(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/api/venues', [
            "name" => "Test Venue",
            "address" => "Test Address",
            "description" => "Test Description",
            "capacity" => 100,
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('venues', [
            "name" => "Test Venue",
            "address" => "Test Address",
            "description" => "Test Description",
            "capacity" => 100,
        ]);

        $this->assertLog("venue_created", user_id: $user->id);
    }

    public function testFailedVenueCreation(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/api/venues', [
            "name" => "",
            "address" => "",
            "description" => "",
            "capacity" => "",
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["name", "address", "description", "capacity"]);
    }

    public function testVenueList(): void
    {
        $user = User::factory()->create();
        $venues = Venue::factory()
            ->count(3)
            ->state(new Sequence(
                ["created_at" => now()],
                ["created_at" => now()->subMinute()],
                ["created_at" => now()->subMinutes(3)],
            ))
            ->create();
        $response = $this->actingAs($user)->get('/api/venues');
        $response->assertStatus(200);
        $response->assertJsonCount(3, "data");

        foreach ($venues as $venue) {
            $response->assertJsonFragment([
                "id" => $venue->id,
                "name" => $venue->name,
                "address" => $venue->address,
                "description" => $venue->description,
                "capacity" => $venue->capacity,
            ]);
        }
    }

    public function testUpdateVenue(): void
    {
        $user = User::factory()->create();
        $venue = Venue::factory()->create();
        $newData = [
            "name" => "Updated Venue",
            "address" => "Updated Address",
            "description" => "Updated Description",
            "capacity" => 200,
        ];
        $response = $this->actingAs($user)->put("/api/venues/{$venue->id}", $newData);
        $response->assertOk();
        $venue->refresh();

        $this->assertEquals("Updated Venue", $venue->name);
        $this->assertEquals("Updated Address", $venue->address);
        $this->assertEquals("Updated Description", $venue->description);
        $this->assertEquals(200, $venue->capacity);

        $this->assertLog("venue_updated", user_id: $user->id, parameters: $newData);
    }

    public function testDeleteUnusedVenue(): void
    {
        $user = User::factory()->create();
        $venue = Venue::factory()->create();
        $response = $this->actingAs($user)->delete("/api/venues/{$venue->id}");
        $response->assertOk();

        $this->assertSoftDeleted("venues", ["id" => $venue->id]);

        $this->assertLog("venue_deleted", user_id: $user->id);
    }

    public function testForbidDeleteUsedVenue(): void
    {
        $user = User::factory()->create();
        $venue = Venue::factory()->create();
        Event::factory()->create(["venue_id" => $venue->id]);
        $response = $this->actingAs($user)->delete("/api/venues/{$venue->id}");
        $response->assertStatus(409);
        $venue->refresh();
        $this->assertNull($venue->deleted_at);
    }
}
