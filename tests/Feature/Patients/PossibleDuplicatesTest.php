<?php

use App\Actions\Patients\PossibleDuplicates;
use App\Actions\Patients\RegisterPatient;
use App\Models\Patient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Carbon::setTestNow('2026-11-22 10:00'));
afterEach(fn () => Carbon::setTestNow());

function registrationError(array $input): ?string
{
    try {
        app(RegisterPatient::class)->handle($input);
    } catch (ValidationException $e) {
        return $e->errors()['phone'][0] ?? null;
    }

    return null;
}

describe('phone', function () {
    it('refuses a phone that already belongs to another patient, naming them', function () {
        Patient::factory()->create(['patient_code' => 'FD-10-26-000007', 'full_name' => 'Nusrat Jahan', 'phone' => '01711000000']);

        expect(registrationError(['full_name' => 'Rakib Hossain', 'phone' => '+880 1711-000000']))
            ->toBe('This phone is already registered to FD-10-26-000007 (Nusrat Jahan). Each patient needs their own number.')
            ->and(Patient::count())->toBe(1);
    });

    it('also counts inactive and merged records as owners', function () {
        $kept = Patient::factory()->create();
        Patient::factory()->inactive()->create(['phone' => '01711000001']);
        Patient::factory()->create(['phone' => '01711000002'])->forceFill(['merged_into_id' => $kept->id])->save();

        expect(registrationError(['full_name' => 'A B', 'phone' => '01711000001']))->not->toBeNull()
            ->and(registrationError(['full_name' => 'A B', 'phone' => '01711000002']))->not->toBeNull();
    });

    it('allows many patients without a phone', function () {
        Patient::factory()->create(['phone' => null]);

        expect(registrationError(['full_name' => 'Small Child']))->toBeNull()
            ->and(registrationError(['full_name' => 'Another Child', 'phone' => '  ']))->toBeNull()
            ->and(Patient::whereNull('phone')->count())->toBe(3);
    });

    it("allows someone else's number as the second phone (e.g. a parent's)", function () {
        Patient::factory()->create(['phone' => '01711000000']);

        expect(registrationError(['full_name' => 'Child', 'phone' => '01811000000', 'alt_phone' => '01711000000']))->toBeNull();
    });

    it('ignores the patient being edited', function () {
        $patient = Patient::factory()->create(['phone' => '01711000000']);

        expect(PossibleDuplicates::phoneOwner('01711000000', $patient->id))->toBeNull()
            ->and(PossibleDuplicates::phoneOwner('01711000000')?->is($patient))->toBeTrue();
    });

    it('is enforced by the database too', function () {
        Patient::factory()->create(['phone' => '01711000000']);

        Patient::factory()->create(['phone' => '01711000000']);
    })->throws(UniqueConstraintViolationException::class);

    it('turns a same-moment clash into a normal phone error', function () {
        // Another desk saves the same number between our check and our insert.
        Patient::creating(function (Patient $p) {
            if ($p->full_name === 'Second Desk') {
                Patient::withoutEvents(fn () => Patient::factory()->create(['phone' => $p->phone]));
            }
        });

        expect(registrationError(['full_name' => 'Second Desk', 'phone' => '01711000000']))
            ->toStartWith('This phone is already registered to');
    });
});

describe('name and age', function () {
    it('finds the same name written differently', function (string $typed) {
        $existing = Patient::factory()->create(['full_name' => 'Md. Rakib Hossain', 'date_of_birth' => '1990-05-01']);

        expect(PossibleDuplicates::byNameAndAge($typed, '1990-05-01')->pluck('id')->all())->toBe([$existing->id]);
    })->with([
        'same' => 'Md. Rakib Hossain',
        'case and spaces' => '  rakib   HOSSAIN ',
        'without Md' => 'Rakib Hossain',
        'other title spelling' => 'Mohammad Rakib Hossain',
        'word order' => 'Hossain Rakib',
    ]);

    it('does not match a different name', function () {
        Patient::factory()->create(['full_name' => 'Rakib Hossain']);

        expect(PossibleDuplicates::byNameAndAge('Rakibul Hossain'))->toBeEmpty()
            ->and(PossibleDuplicates::byNameAndAge('Rakib Hasan'))->toBeEmpty()
            ->and(PossibleDuplicates::byNameAndAge('  '))->toBeEmpty();
    });

    it('matches only similar ages when both are known', function () {
        Patient::factory()->create(['full_name' => 'Nusrat Jahan', 'date_of_birth' => '1992-01-15', 'age_years' => null]); // 34
        Patient::factory()->create(['full_name' => 'Nusrat Jahan', 'date_of_birth' => null, 'age_years' => 8, 'age_recorded_on' => '2026-01-01']);

        expect(PossibleDuplicates::byNameAndAge('Nusrat Jahan', ageYears: 35))->toHaveCount(1)
            ->and(PossibleDuplicates::byNameAndAge('Nusrat Jahan', '1992-01-15')->first()->age())->toBe(34)
            ->and(PossibleDuplicates::byNameAndAge('Nusrat Jahan', ageYears: 60))->toBeEmpty();
    });

    it('matches on the name alone when an age is unknown', function () {
        Patient::factory()->create(['full_name' => 'Nusrat Jahan', 'date_of_birth' => null, 'age_years' => null]);
        Patient::factory()->create(['full_name' => 'Nusrat Jahan', 'date_of_birth' => '1960-01-01', 'age_years' => null]);

        expect(PossibleDuplicates::byNameAndAge('Nusrat Jahan'))->toHaveCount(2)
            ->and(PossibleDuplicates::byNameAndAge('Nusrat Jahan', ageYears: 30))->toHaveCount(1);
    });

    it('leaves out merged records and the patient being edited, but keeps inactive ones', function () {
        $kept = Patient::factory()->create(['full_name' => 'Nusrat Jahan', 'date_of_birth' => null, 'age_years' => null]);
        $inactive = Patient::factory()->inactive()->create(['full_name' => 'Nusrat Jahan', 'date_of_birth' => null, 'age_years' => null]);
        Patient::factory()->create(['full_name' => 'Nusrat Jahan'])->forceFill(['merged_into_id' => $kept->id])->save();

        expect(PossibleDuplicates::byNameAndAge('Nusrat Jahan', ignoreId: $kept->id)->pluck('id')->all())->toBe([$inactive->id]);
    });

    it('does not block registration', function () {
        Patient::factory()->create(['full_name' => 'Nusrat Jahan', 'date_of_birth' => '1992-01-15']);

        $patient = app(RegisterPatient::class)->handle(['full_name' => 'Nusrat Jahan', 'date_of_birth' => '1992-01-15']);

        expect($patient->exists)->toBeTrue();
    });
});
