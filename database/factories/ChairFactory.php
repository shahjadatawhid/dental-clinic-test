<?php

namespace Database\Factories;

use App\Models\Chair;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chair>
 */
class ChairFactory extends Factory
{
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'name' => 'Chair '.fake()->unique()->randomLetter().fake()->numberBetween(1, 99),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
