<?php

use App\Actions\Patients\PossibleDuplicates;
use App\Actions\Patients\RegisterPatient;
use App\Models\Patient;
use Illuminate\Support\Carbon;

beforeEach(fn () => Carbon::setTestNow('2026-11-22 10:00'));
afterEach(fn () => Carbon::setTestNow());

/** @return list<array{int, string}> [patient id, match] pairs */
function matchesFor(array $input): array
{
    return PossibleDuplicates::check($input)->map(fn ($m) => [$m['patient']->id, $m['match']])->all();
}

describe('shared phone', function () {
    it('lets a family share one number, each patient getting their own code', function () {
        $register = app(RegisterPatient::class);

        $mother = $register->handle(['full_name' => 'Nusrat Jahan', 'phone' => '01711000000']);
        $son = $register->handle(['full_name' => 'Arian Hossain', 'age_years' => 6, 'phone' => '+880 1711-000000']);
        $daughter = $register->handle(['full_name' => 'Anika Hossain', 'age_years' => 3, 'phone' => '01711000000']);

        expect([$mother->patient_code, $son->patient_code, $daughter->patient_code])
            ->toBe(['FD-11-26-000001', 'FD-11-26-000002', 'FD-11-26-000003'])
            ->and(Patient::where('phone', '01711000000')->count())->toBe(3);
    });

    it('lists the family already on the number (phone or second phone)', function () {
        $mother = Patient::factory()->create(['full_name' => 'Nusrat Jahan', 'phone' => '01711000000']);
        $father = Patient::factory()->create(['full_name' => 'Kamal Hossain', 'phone' => '01811000000', 'alt_phone' => '01711000000']);
        Patient::factory()->create(['phone' => '01911000000']);

        expect(matchesFor(['full_name' => 'Arian Hossain', 'age_years' => 6, 'phone' => '+880 1711-000000']))->toBe([
            [$mother->id, PossibleDuplicates::SAME_PHONE],
            [$father->id, PossibleDuplicates::SAME_PHONE],
        ]);
    });

    it('flags same phone + same name as probably the same person, first', function () {
        $mother = Patient::factory()->create(['full_name' => 'Nusrat Jahan', 'phone' => '01711000000']);
        $existing = Patient::factory()->create(['full_name' => 'Md. Arian Hossain', 'phone' => '01711000000']);

        expect(matchesFor(['full_name' => 'arian hossain', 'phone' => '01711000000']))->toBe([
            [$existing->id, PossibleDuplicates::SAME_PERSON],
            [$mother->id, PossibleDuplicates::SAME_PHONE],
        ]);
    });

    it('lists a same-name patient on another phone between the two', function () {
        $sibling = Patient::factory()->create(['full_name' => 'Anika Hossain', 'phone' => '01711000000']);
        $namesake = Patient::factory()->create(['full_name' => 'Arian Hossain', 'phone' => '01811000000', 'date_of_birth' => null, 'age_years' => null]);

        expect(matchesFor(['full_name' => 'Arian Hossain', 'phone' => '01711000000']))->toBe([
            [$namesake->id, PossibleDuplicates::SAME_NAME],
            [$sibling->id, PossibleDuplicates::SAME_PHONE],
        ]);
    });

    it('leaves out merged records and the patient being edited, keeps inactive ones', function () {
        $kept = Patient::factory()->create(['phone' => '01711000000']);
        $inactive = Patient::factory()->inactive()->create(['phone' => '01711000000']);
        Patient::factory()->create(['phone' => '01711000000'])->forceFill(['merged_into_id' => $kept->id])->save();

        expect(PossibleDuplicates::samePhone(['01711000000'], $kept->id)->pluck('id')->all())->toBe([$inactive->id]);
    });

    it('finds nothing for a blank or half-typed phone', function () {
        Patient::factory()->create(['full_name' => 'Nusrat Jahan', 'phone' => '01711000000']);

        expect(matchesFor(['full_name' => 'X Y', 'phone' => '']))->toBe([])
            ->and(matchesFor(['full_name' => 'X Y', 'phone' => '0171']))->toBe([]);
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
