<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "event_id" => Event::factory(),
            "name" => $this->faker->name(),
            "description" => $this->faker->paragraph(),
            "price" => $this->faker->numberBetween(10_000, 500_000),
            "quantity" => $this->faker->numberBetween(1, 500),
            "sold_quantity" => 0
        ];
    }
}
