<?php

use App\Authorization\AuditLogAccess;
use App\Enums\RoleName;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => Carbon::setTestNow('2026-11-22 10:00'));
afterEach(fn () => Carbon::setTestNow());

describe('age', function () {
    it('is worked out from the date of birth', function () {
        $patient = Patient::factory()->make(['date_of_birth' => '1990-12-01', 'age_years' => null]);

        expect($patient->age())->toBe(35)
            ->and($patient->age(Carbon::parse('2026-12-01')))->toBe(36);
    });

    it('grows from the age given at registration when the birthday is unknown', function () {
        $patient = Patient::factory()->make([
            'date_of_birth' => null, 'age_years' => 30, 'age_recorded_on' => '2024-06-01',
        ]);

        expect($patient->age())->toBe(32);
    });

    it('is unknown when neither is recorded', function () {
        expect(Patient::factory()->make(['date_of_birth' => null, 'age_years' => null])->age())->toBeNull();
    });

    it('shows a short summary for headers', function () {
        $patient = Patient::factory()->make(['full_name' => 'Nusrat Jahan', 'gender' => 'female', 'date_of_birth' => '1992-01-15']);

        expect($patient->summary())->toBe('Nusrat Jahan · F · 34y');
    });
});

describe('records', function () {
    it('keeps patient codes unique', function () {
        Patient::factory()->create(['patient_code' => 'FD-11-26-000001']);

        Patient::factory()->create(['patient_code' => 'FD-11-26-000001']);
    })->throws(QueryException::class);

    it('allows the same legacy code twice (old sheets may contain duplicates to review)', function () {
        Patient::factory()->count(2)->withLegacyCode('FD-01-23-0208')->create();

        expect(Patient::where('legacy_code', 'FD-01-23-0208')->count())->toBe(2);
    });

    it('treats inactive and merged records as not active', function () {
        $kept = Patient::factory()->create();
        Patient::factory()->inactive()->create();
        $merged = Patient::factory()->create();
        $merged->forceFill(['merged_into_id' => $kept->id])->save();

        expect(Patient::active()->pluck('id')->all())->toBe([$kept->id])
            ->and($merged->fresh()->mergedInto->is($kept))->toBeTrue();
    });

    it('refuses to delete a patient another record was merged into', function () {
        $kept = Patient::factory()->create();
        Patient::factory()->create()->forceFill(['merged_into_id' => $kept->id])->save();

        $kept->delete();
    })->throws(QueryException::class);

    it('records who registered and changed the patient', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $patient = Patient::factory()->create();
        $patient->update(['phone' => '01811111111']);

        expect($patient->fresh()->created_by)->toBe($user->id)
            ->and(Activity::where('log_name', 'patients')->where('event', 'updated')->first()->attribute_changes['attributes'])
            ->toBe(['phone' => '01811111111']);
    });
});

it('hides patient audit entries from roles that cannot see patients', function () {
    $this->artisan('app:sync-permissions');

    expect(AuditLogAccess::hiddenFor(User::factory()->withRole(RoleName::SystemAdmin)->create()))->toContain('patients')
        ->and(AuditLogAccess::hiddenFor(User::factory()->withRole(RoleName::Manager)->create()))->not->toContain('patients');
});
