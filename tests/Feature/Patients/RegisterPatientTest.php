<?php

use App\Actions\Patients\RegisterPatient;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Carbon::setTestNow('2026-11-22 10:00');
    $this->register = app(RegisterPatient::class);
});

afterEach(fn () => Carbon::setTestNow());

it('registers a patient with the next clinic code', function () {
    $this->actingAs($user = User::factory()->create());

    $first = $this->register->handle(['full_name' => 'Nusrat Jahan', 'phone' => '01711000000']);
    $second = $this->register->handle(['full_name' => 'Rakib Hossain']);

    expect($first->patient_code)->toBe('FD-11-26-000001')
        ->and($second->patient_code)->toBe('FD-11-26-000002')
        ->and($first->registered_on->toDateString())->toBe('2026-11-22')
        ->and($first->preferred_language)->toBe('bn')
        ->and($first->is_active)->toBeTrue()
        ->and($first->created_by)->toBe($user->id);
});

it('uses the registration date for back-dated registrations', function () {
    $patient = $this->register->handle(['full_name' => 'Old Patient', 'registered_on' => '2026-09-03']);

    expect($patient->patient_code)->toBe('FD-09-26-000001');
});

it('cleans up what reception types', function () {
    $patient = $this->register->handle([
        'full_name' => '  Nusrat    Jahan ',
        'phone' => '+880 1711-000000',
        'alt_phone' => '01811 000000',
        'email' => ' Nusrat@Example.COM ',
        'legacy_code' => ' fd-01-23-0208 ',
        'address' => '   ',
    ]);

    expect($patient->full_name)->toBe('Nusrat Jahan')
        ->and($patient->phone)->toBe('01711000000')
        ->and($patient->alt_phone)->toBe('01811000000')
        ->and($patient->email)->toBe('nusrat@example.com')
        ->and($patient->legacy_code)->toBe('FD-01-23-0208')
        ->and($patient->address)->toBeNull();
});

it('remembers the age and the day it was given when the birthday is unknown', function () {
    $patient = $this->register->handle(['full_name' => 'A Patient', 'age_years' => 40]);

    expect($patient->age_years)->toBe(40)
        ->and($patient->age_recorded_on->toDateString())->toBe('2026-11-22')
        ->and($patient->fresh()->age(Carbon::parse('2028-12-01')))->toBe(42);
});

it('prefers the date of birth over a typed age', function () {
    $patient = $this->register->handle(['full_name' => 'A Patient', 'date_of_birth' => '1990-01-01', 'age_years' => 99]);

    expect($patient->age_years)->toBeNull()
        ->and($patient->age_recorded_on)->toBeNull()
        ->and($patient->age())->toBe(36);
});

it('rejects invalid details without using up a code', function (array $input, string $field) {
    try {
        $this->register->handle($input);
        $this->fail('Expected a validation error');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }

    expect(Patient::count())->toBe(0)
        ->and($this->register->handle(['full_name' => 'Next One'])->patient_code)->toBe('FD-11-26-000001');
})->with([
    'no name' => [['full_name' => ''], 'full_name'],
    'bad phone' => [['full_name' => 'A B', 'phone' => '12345'], 'phone'],
    'same second phone' => [['full_name' => 'A B', 'phone' => '01711000000', 'alt_phone' => '01711000000'], 'alt_phone'],
    'future birthday' => [['full_name' => 'A B', 'date_of_birth' => '2027-01-01'], 'date_of_birth'],
    'impossible age' => [['full_name' => 'A B', 'age_years' => 150], 'age_years'],
    'unknown gender' => [['full_name' => 'A B', 'gender' => 'x'], 'gender'],
    'future registration' => [['full_name' => 'A B', 'registered_on' => '2026-12-01'], 'registered_on'],
]);

it('releases the code if the surrounding save fails', function () {
    try {
        DB::transaction(function () {
            $this->register->handle(['full_name' => 'Rolled Back']);
            throw new RuntimeException('Something later failed');
        });
    } catch (RuntimeException) {
    }

    expect(Patient::count())->toBe(0)
        ->and($this->register->handle(['full_name' => 'Kept'])->patient_code)->toBe('FD-11-26-000001');
});

it('ignores attempts to set protected fields', function () {
    $other = Patient::factory()->create();

    $patient = $this->register->handle([
        'full_name' => 'Sneaky', 'patient_code' => 'FD-HACKED', 'merged_into_id' => $other->id, 'is_active' => false,
    ]);

    expect($patient->patient_code)->toBe('FD-11-26-000001')
        ->and($patient->merged_into_id)->toBeNull()
        ->and($patient->is_active)->toBeTrue();
});
