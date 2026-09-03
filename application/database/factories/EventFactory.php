<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'user_id' => $this->faker->randomNumber(),
            'name' => $this->faker->name(),
            'description' => $this->faker->text(),
            'started_at' => Carbon::now(),
            'ended_at' => Carbon::now(),
            'location' => $this->faker->word(),
            'organizer' => $this->faker->word(),
            'category' => $this->faker->word(),
            'status' => $this->faker->randomElement(EventStatus::cases()),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
