<?php

use App\Actions\Patients\PossibleDuplicates;
use App\Models\Patient;
use Database\Seeders\PatientSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-22 10:00');
    config(['demo.patients' => 120]);
});

afterEach(fn () => Carbon::setTestNow());

it('registers the configured number of fictional patients, topping up when run again', function () {
    $this->seed(PatientSeeder::class);
    expect(Patient::count())->toBe(120);

    $this->seed(PatientSeeder::class);
    expect(Patient::count())->toBe(120);

    config(['demo.patients' => 130]);
    $this->seed(PatientSeeder::class);
    expect(Patient::count())->toBe(130);
});

it('gives clinic-format codes in registration order', function () {
    $this->seed(PatientSeeder::class);

    $patients = Patient::orderBy('id')->get();

    expect($patients->every(fn (Patient $p) => preg_match('/^FD-\d{2}-\d{2}-\d{6}$/', $p->patient_code) === 1))->toBeTrue()
        ->and($patients->pluck('patient_code')->unique())->toHaveCount(120)
        ->and($patients->pluck('registered_on')->map->toDateString()->all())
        ->toBe($patients->pluck('registered_on')->map->toDateString()->sort()->values()->all())
        ->and($patients->every(fn (Patient $p) => $p->registered_on->between('2023-11-22', '2026-11-22')))->toBeTrue();
});

it('includes families on one phone, with children under their guardian', function () {
    $this->seed(PatientSeeder::class);

    $children = Patient::whereNotNull('guardian_name')->get();
    $child = $children->first();

    expect($children)->not->toBeEmpty()
        ->and($children->every(fn (Patient $c) => $c->age($c->registered_on) < 18))->toBeTrue()
        ->and($children->every(fn (Patient $c) => in_array($c->guardian_relation, ['mother', 'father'], true)))->toBeTrue()
        ->and(Patient::where('phone', $child->phone)->where('full_name', $child->guardian_name)->exists())->toBeTrue()
        ->and(PossibleDuplicates::samePhone([$child->phone], $child->id))->not->toBeEmpty();
});

it('mixes in old IDs, age-only adults and inactive patients', function () {
    $this->seed(PatientSeeder::class);

    expect(Patient::whereNotNull('legacy_code')->count())->toBeGreaterThan(10)
        ->and(Patient::whereNull('date_of_birth')->whereNotNull('age_years')->count())->toBeGreaterThan(5)
        ->and(Patient::where('is_active', false)->count())->toBeGreaterThan(0)
        ->and(Patient::whereNotNull('date_of_birth')->where('date_of_birth', '>', today())->count())->toBe(0);
});
