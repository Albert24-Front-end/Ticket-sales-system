<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            "organizer_id" => User::factory(),
            "name" => $this->faker->name(),
            "description" => $this->faker->paragraph(),
            "started_at" => Carbon::now()->addDays(10),
            "ended_at" => Carbon::now()->addDays(10)->addHours(3),
            "venue_id" => Venue::factory(),
            "category_id" => Category::factory(),
            "status" => EventStatus::DRAFT,
            "created_at" => Carbon::now(),
            "updated_at" => Carbon::now(),
        ];
    }
}
