<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class CategoryTest extends TestCase
{
    use RefreshDatabase, WithAuditLogs, WithFaker;
    /**
     * A basic feature test example.
     */
    public function testSuccessfulCategoryCreation(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/api/categories', [
            "name" => "Test Category",
            "description" => "Test Description",
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', [
            "name" => "Test Category",
            "description" => "Test Description",
        ]);

        $this->assertLog("category_created", user_id: $user->id);
    }

    public function testFailedCategoryCreation(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/api/categories', [
            "name" => "",
            "description" => "",
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["name", "description"]);
    }

    public function testCategoryList(): void
    {
        $user = User::factory()->create();
        $categories = Category::factory()
            ->count(3)
            ->state(new Sequence(
                ["created_at" => now()],
                ["created_at" => now()->subMinute()],
                ["created_at" => now()->subMinutes(3)],
            ))
            ->create();
        $response = $this->actingAs($user)->get('/api/categories');
        $response->assertStatus(200);
        $response->assertJsonCount(3, "data");

        foreach ($categories as $category) {
            $response->assertJsonFragment([
                "id" => $category->id,
                "name" => $category->name,
                "description" => $category->description,
            ]);
        }
    }

    public function testUpdateCategory(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $newData = [
            "name" => "Updated Category",
            "description" => "Updated Description",
        ];
        $response = $this->actingAs($user)->put("/api/categories/{$category->id}", $newData);
        $response->assertOk();
        $category->refresh();

        $this->assertEquals("Updated Category", $category->name);
        $this->assertEquals("Updated Description", $category->description);

        $this->assertLog("category_updated", user_id: $user->id, parameters: $newData);
    }

    public function testDeleteUnusedCategory(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $response = $this->actingAs($user)->delete("/api/categories/{$category->id}");
        $response->assertOk();

        $this->assertSoftDeleted("categories", ["id" => $category->id]);

        $this->assertLog("category_deleted", user_id: $user->id);
    }

    public function testForbidDeleteUsedCategory(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Event::factory()->create(["category_id" => $category->id]);
        $response = $this->actingAs($user)->delete("/api/categories/{$category->id}");
        $response->assertStatus(409);
        $category->refresh();
        $this->assertNull($category->deleted_at);
    }
}
