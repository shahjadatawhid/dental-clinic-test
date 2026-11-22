<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'staff_id' => Staff::factory()->doctor(),
            'registration_no' => 'TEST-'.fake()->unique()->numerify('#####'), // fictional, clearly not a real BMDC number
            'specialty' => fake()->randomElement([
                'General dentistry', 'Orthodontics', 'Endodontics', 'Prosthodontics',
                'Oral & maxillofacial surgery', 'Paediatric dentistry', 'Periodontics',
            ]),
            'qualifications' => fake()->randomElement(['BDS', 'BDS, FCPS', 'BDS, MS', 'BDS, PGT']),
            'calendar_color' => fake()->randomElement(['#0ea5e9', '#22c55e', '#f59e0b', '#a855f7', '#ef4444', '#14b8a6']),
            'default_slot_minutes' => null,
            'is_bookable' => true,
        ];
    }

    public function notBookable(): static
    {
        return $this->state(fn () => ['is_bookable' => false]);
    }
}
