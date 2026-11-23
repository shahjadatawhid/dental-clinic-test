<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictional patients. Codes use a TEST- prefix so they can never be mistaken for real
 * clinic codes; RegisterPatient assigns real-format codes.
 *
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);
        $knowsBirthday = fake()->boolean(70);
        $registered = fake()->dateTimeBetween(now()->subYears(3), now()); // Carbon now(): respects frozen test time

        return [
            'patient_code' => 'TEST-'.fake()->unique()->numerify('#######'),
            'registered_on' => $registered,
            'full_name' => fake_bd()->name($gender),
            'gender' => $gender,
            'date_of_birth' => $knowsBirthday ? fake()->dateTimeBetween(now()->subYears(80), now()->subYears(3)) : null,
            'age_years' => $knowsBirthday ? null : fake()->numberBetween(5, 75),
            'age_recorded_on' => $knowsBirthday ? null : $registered,
            'phone' => fake_bd()->phone(),
            'address' => fake_bd()->address(),
            'occupation' => fake()->optional(0.6)->randomElement(['Student', 'Teacher', 'Business', 'Service holder', 'Housewife', 'Doctor', 'Engineer', 'Retired']),
            'preferred_language' => fake()->randomElement(['bn', 'bn', 'bn', 'en']),
            'is_active' => true,
        ];
    }

    public function withLegacyCode(?string $code = null): static
    {
        return $this->state(fn () => [
            'legacy_code' => $code ?? sprintf('FD-%02d-%02d-%04d', fake()->numberBetween(1, 12), fake()->numberBetween(19, 25), fake()->unique()->numberBetween(1, 9999)),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
