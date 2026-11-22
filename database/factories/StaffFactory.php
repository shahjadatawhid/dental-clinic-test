<?php

namespace Database\Factories;

use App\Enums\StaffType;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'full_name' => fake_bd()->name($gender),
            'type' => StaffType::Staff,
            'designation' => fake()->randomElement(['Dental assistant', 'Receptionist', 'Nurse', 'Cashier', 'Cleaner']),
            'gender' => $gender,
            'phone' => fake_bd()->phone(),
            'email' => null,
            'joined_on' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'is_active' => true,
        ];
    }

    public function doctor(): static
    {
        return $this->state(fn () => [
            'full_name' => 'Dr. '.fake_bd()->name(),
            'type' => StaffType::Doctor,
            'designation' => 'Dental surgeon',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false, 'left_on' => now()->subMonth()]);
    }

    /** Link to a new login account (email/password from UserFactory). */
    public function withUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->state(['name' => $attributes['full_name']]),
        ]);
    }
}
