<?php

namespace Database\Seeders;

use App\Actions\Patients\RegisterPatient;
use App\Models\Patient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * M03 demo data: fictional patients registered over the last three years, through
 * RegisterPatient so they get real-format codes (FD-MM-YY-NNNNNN) in date order.
 *
 * Mix, to exercise search and the duplicate checks:
 *  - families sharing one phone (a parent + children with the parent as guardian)
 *  - some adults with only an age (birthday unknown), some with a second phone
 *  - about a third with an old (legacy) ID, a few inactive
 *  - a few "probably the same person" re-registrations, for trying merge later
 *
 * Tops up to config('demo.patients') (DEMO_PATIENTS, default 2,000); safe to run again.
 */
class PatientSeeder extends Seeder
{
    use GuardsAgainstProduction;

    /** Family names that are only used by women, so children take another family name. */
    private const FEMALE_ONLY_FAMILY_NAMES = ['Akter', 'Begum', 'Jahan', 'Khatun', 'Sultana'];

    private int $legacyNumber = 0;

    public function __construct(private RegisterPatient $register) {}

    public function run(): void
    {
        $this->refuseInProduction();

        $missing = (int) config('demo.patients') - Patient::count();
        if ($missing <= 0) {
            return;
        }

        $this->legacyNumber = Patient::whereNotNull('legacy_code')->count();
        $dates = collect(range(1, $missing))
            ->map(fn () => Carbon::instance(fake()->dateTimeBetween(now()->subYears(3), now()))->startOfDay())
            ->sort()->values();

        $created = 0;
        while ($created < $missing) {
            $on = $dates[$created];
            $roll = fake()->numberBetween(1, 100);

            if ($roll <= 20 && $missing - $created >= 2) {
                $created += $this->family($on, min(fake()->numberBetween(2, 4), $missing - $created));
            } elseif ($roll <= 21 && $created > 10) {
                $this->sameAgain($on);
                $created++;
            } else {
                $this->adult($on);
                $created++;
            }
        }

        // A few patients who stopped coming.
        Patient::query()->inRandomOrder()->limit((int) ceil($missing * 0.03))->update(['is_active' => false]);
    }

    private function adult(Carbon $on, ?string $gender = null, ?string $phone = null): Patient
    {
        $gender ??= fake()->randomElement(['male', 'female']);
        $knowsBirthday = fake()->boolean(65);
        $age = fake()->numberBetween(18, 75);

        return $this->register->handle([
            'full_name' => fake_bd()->name($gender),
            'gender' => $gender,
            'date_of_birth' => $knowsBirthday ? $this->birthday($age, $on) : null,
            'age_years' => $knowsBirthday ? null : $age,
            'phone' => $phone ?? fake_bd()->phone(),
            'alt_phone' => fake()->boolean(10) ? fake_bd()->phone() : null,
            'email' => fake()->boolean(15) ? fake()->unique()->safeEmail() : null,
            'address' => fake_bd()->address(),
            'occupation' => fake()->optional(0.6)->randomElement(['Student', 'Teacher', 'Business', 'Service holder', 'Housewife', 'Doctor', 'Engineer', 'Retired', 'Banker', 'Driver']),
            'preferred_language' => fake()->randomElement(['bn', 'bn', 'bn', 'en']),
            'referred_by' => fake()->boolean(10) ? fake_bd()->name() : null,
            'legacy_code' => $this->maybeLegacyCode($on),
            'registered_on' => $on->toDateString(),
        ]);
    }

    /** A parent and their children, all on the parent's phone. Returns how many were registered. */
    private function family(Carbon $on, int $size): int
    {
        $parent = $this->adult($on, fake()->randomElement(['female', 'female', 'male']), fake_bd()->phone());
        $family = last(explode(' ', $parent->full_name));
        if (in_array($family, self::FEMALE_ONLY_FAMILY_NAMES, true)) {
            $family = fake()->randomElement(['Hossain', 'Rahman', 'Islam', 'Ahmed', 'Chowdhury']);
        }

        foreach (range(2, $size) as $i) {
            $gender = fake()->randomElement(['male', 'female']);
            $age = fake()->numberBetween(2, 16);

            $this->register->handle([
                'full_name' => fake_bd()->firstName($gender).' '.$family,
                'gender' => $gender,
                'date_of_birth' => fake()->boolean(80) ? $this->birthday($age, $on) : null,
                'age_years' => $age,
                'phone' => $parent->phone,
                'address' => $parent->address,
                'preferred_language' => $parent->preferred_language,
                'guardian_name' => $parent->full_name,
                'guardian_relation' => $parent->gender === 'female' ? 'mother' : 'father',
                'legacy_code' => $this->maybeLegacyCode($on),
                'registered_on' => $on->toDateString(),
            ]);
        }

        return $size;
    }

    /** Someone registered a second time by mistake: same name and phone. */
    private function sameAgain(Carbon $on): void
    {
        $original = Patient::query()->whereNotNull('phone')->whereNull('guardian_name')->inRandomOrder()->first();
        if ($original === null) {
            $this->adult($on);

            return;
        }

        $this->register->handle([
            'full_name' => $original->full_name,
            'gender' => $original->gender,
            'age_years' => $original->age($on),
            'phone' => $original->phone,
            'registered_on' => $on->toDateString(),
            'notes' => 'Demo: possible duplicate of '.$original->patient_code,
        ]);
    }

    private function birthday(int $age, Carbon $on): string
    {
        return $on->copy()->subYears($age)->subDays(fake()->numberBetween(1, 364))->toDateString();
    }

    /** Old register IDs like FD-01-23-0208, only for patients from before this system. */
    private function maybeLegacyCode(Carbon $on): ?string
    {
        if (! fake()->boolean(35)) {
            return null;
        }

        return sprintf('FD-%02d-%02d-%04d', $on->month, $on->format('y'), ++$this->legacyNumber % 10000);
    }
}
