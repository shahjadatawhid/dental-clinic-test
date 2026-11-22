<?php

use App\Actions\Patients\UpdatePatient;
use App\Enums\RoleName;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow('2026-11-22 10:00');
    $this->artisan('app:sync-permissions');
    $this->actingAs(User::factory()->withRole(RoleName::Receptionist)->create());
});

afterEach(fn () => Carbon::setTestNow());

describe('register', function () {
    it('registers a patient and shows the new code', function () {
        Volt::test('patients.form')
            ->set('form.full_name', 'Arian Hossain')
            ->set('form.gender', 'male')
            ->set('form.age_years', '6')
            ->set('form.phone', '01711 000000')
            ->set('form.guardian_name', 'Nusrat Jahan')
            ->set('form.guardian_relation', 'mother')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('patients.index', ['search' => 'FD-11-26-000001']));

        $patient = Patient::sole();
        expect($patient->patient_code)->toBe('FD-11-26-000001')
            ->and($patient->phone)->toBe('01711000000')
            ->and($patient->age())->toBe(6)
            ->and($patient->guardian_name)->toBe('Nusrat Jahan')
            ->and($patient->guardian_relation)->toBe('mother')
            ->and(session('status'))->toBe('Arian Hossain has been registered as FD-11-26-000001.');
    });

    it('shows validation errors next to the fields', function () {
        Volt::test('patients.form')
            ->set('form.full_name', '')
            ->set('form.phone', '12345')
            ->set('form.guardian_relation', 'neighbour')
            ->call('save')
            ->assertHasErrors(['form.full_name', 'form.phone', 'form.guardian_relation']);

        expect(Patient::count())->toBe(0);
    });

    it('asks for a guardian when the patient is a child', function () {
        Volt::test('patients.form')
            ->set('form.age_years', '30')->assertDontSee('This patient is a child')
            ->set('form.age_years', '7')->assertSee('This patient is a child')
            ->set('form.age_years', '')->set('form.date_of_birth', '2020-05-01')->assertSee('This patient is a child');
    });

    it('starts with what was typed in an empty search', function () {
        $this->get(route('patients.create', ['name' => 'Rahim Uddin']))->assertOk()->assertSee('Rahim Uddin');

        // A number goes into the mobile field (seen in the component's state), a name into the name field.
        $this->get(route('patients.create', ['name' => '01711000000']))->assertOk()->assertSee('&quot;phone&quot;:&quot;01711000000&quot;', false);
    });
});

describe('possible duplicates on the form', function () {
    beforeEach(function () {
        $this->mother = Patient::factory()->create(['patient_code' => 'FD-10-26-000001', 'full_name' => 'Nusrat Jahan', 'phone' => '01711000000']);
    });

    it('lists the family on the number and still registers the child', function () {
        Volt::test('patients.form')
            ->set('form.full_name', 'Arian Hossain')
            ->set('form.phone', '01711000000')
            ->assertSee('Others on this number')
            ->assertSee('FD-10-26-000001')
            ->call('save')
            ->assertHasNoErrors();

        expect(Patient::where('phone', '01711000000')->count())->toBe(2);
    });

    it('stops at "probably the same person" until reception confirms', function () {
        $form = Volt::test('patients.form')
            ->set('form.full_name', 'nusrat jahan')
            ->set('form.phone', '01711000000')
            ->assertSee('Probably the same person')
            ->assertSee('This person may already be registered')
            ->call('save')
            ->assertHasErrors('confirmedDifferent');

        expect(Patient::count())->toBe(1);

        $form->set('confirmedDifferent', true)->call('save')->assertHasNoErrors();
        expect(Patient::count())->toBe(2);
    });

    it('asks again when the name or phone changes after confirming', function () {
        Volt::test('patients.form')
            ->set('form.full_name', 'Nusrat Jahan')
            ->set('form.phone', '01711000000')
            ->set('confirmedDifferent', true)
            ->set('form.full_name', 'Md. Nusrat Jahan')
            ->assertSet('confirmedDifferent', false)
            ->set('confirmedDifferent', true)
            ->set('form.email', 'x@example.com')
            ->assertSet('confirmedDifferent', true);
    });

    it('warns about a namesake of the same age on another number without blocking', function () {
        Patient::factory()->create(['full_name' => 'Rakib Hossain', 'phone' => '01811000000', 'date_of_birth' => null, 'age_years' => null]);

        Volt::test('patients.form')
            ->set('form.full_name', 'Rakib Hossain')
            ->set('form.phone', '01911000000')
            ->assertSee('Same name and age')
            ->call('save')
            ->assertHasNoErrors();
    });
});

describe('edit', function () {
    it('edits a patient, keeping the code, and records the change', function () {
        $patient = Patient::factory()->create(['patient_code' => 'FD-10-26-000005', 'full_name' => 'Rakib Hosen', 'phone' => '01711000000']);

        Volt::test('patients.form', ['patient' => $patient])
            ->assertSet('form.full_name', 'Rakib Hosen')
            ->set('form.full_name', 'Rakib Hossain')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('patients.index', ['search' => 'FD-10-26-000005']));

        expect($patient->fresh()->full_name)->toBe('Rakib Hossain')
            ->and($patient->fresh()->patient_code)->toBe('FD-10-26-000005')
            ->and(Activity::where('log_name', 'patients')->where('event', 'updated')->sole()->attribute_changes['attributes'])
            ->toBe(['full_name' => 'Rakib Hossain']);
    });

    it('does not flag the patient as a duplicate of themselves', function () {
        $patient = Patient::factory()->create(['full_name' => 'Nusrat Jahan', 'phone' => '01711000000']);

        Volt::test('patients.form', ['patient' => $patient])->assertDontSee('Probably the same person');
    });

    it('can deactivate a patient', function () {
        $patient = Patient::factory()->create();

        Volt::test('patients.form', ['patient' => $patient])->set('form.is_active', false)->call('save')->assertHasNoErrors();

        expect($patient->fresh()->is_active)->toBeFalse();
    });

    it('is not available for a merged record', function () {
        $kept = Patient::factory()->create();
        $merged = Patient::factory()->create();
        $merged->forceFill(['merged_into_id' => $kept->id])->save();

        $this->get(route('patients.edit', $merged))->assertNotFound();
    });

    it('needs the right permission', function () {
        $patient = Patient::factory()->create();
        $this->actingAs(User::factory()->withRole(RoleName::Doctor)->create()); // view only

        $this->get(route('patients.create'))->assertForbidden();
        $this->get(route('patients.edit', $patient))->assertForbidden();
        $this->get(route('patients.index'))->assertOk()->assertDontSee(__('Register patient'));
    });
});

describe('UpdatePatient age handling', function () {
    it('keeps an unchanged age growing from the day it was first given', function () {
        $patient = Patient::factory()->create(['date_of_birth' => null, 'age_years' => 30, 'age_recorded_on' => '2024-06-01']);

        app(UpdatePatient::class)->handle($patient, ['full_name' => $patient->full_name, 'age_years' => 32]); // the form shows today's age

        expect($patient->fresh()->age_years)->toBe(30)
            ->and($patient->fresh()->age_recorded_on->toDateString())->toBe('2024-06-01');
    });

    it('records a corrected age with today\'s date', function () {
        $patient = Patient::factory()->create(['date_of_birth' => null, 'age_years' => 30, 'age_recorded_on' => '2024-06-01']);

        app(UpdatePatient::class)->handle($patient, ['full_name' => $patient->full_name, 'age_years' => 40]);

        expect($patient->fresh()->age_years)->toBe(40)
            ->and($patient->fresh()->age_recorded_on->toDateString())->toBe('2026-11-22');
    });

    it('replaces the age with a date of birth', function () {
        $patient = Patient::factory()->create(['date_of_birth' => null, 'age_years' => 30, 'age_recorded_on' => '2024-06-01']);

        app(UpdatePatient::class)->handle($patient, ['full_name' => $patient->full_name, 'date_of_birth' => '1990-01-01', 'age_years' => 99]);

        expect($patient->fresh()->age_years)->toBeNull()
            ->and($patient->fresh()->age_recorded_on)->toBeNull()
            ->and($patient->fresh()->age())->toBe(36);
    });

    it('refuses to edit a merged record', function () {
        $kept = Patient::factory()->create();
        $merged = Patient::factory()->create();
        $merged->forceFill(['merged_into_id' => $kept->id])->save();

        app(UpdatePatient::class)->handle($merged->fresh(), ['full_name' => 'New Name']);
    })->throws(ValidationException::class);
});
