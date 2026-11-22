<?php

use App\Enums\RoleName;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

beforeEach(function () {
    Carbon::setTestNow('2026-11-22 10:00');
    $this->artisan('app:sync-permissions');

    $this->receptionist = User::factory()->withRole(RoleName::Receptionist)->create(['name' => 'Front Desk']);
    $this->actingAs($this->receptionist);

    $this->mother = Patient::factory()->create([
        'patient_code' => 'FD-11-26-000001', 'full_name' => 'Nusrat Jahan', 'gender' => 'female',
        'date_of_birth' => '1992-01-15', 'phone' => '01711000000',
    ]);
    $this->child = Patient::factory()->withLegacyCode('FD-01-23-0208')->create([
        'patient_code' => 'FD-11-26-000002', 'full_name' => 'Arian Hossain', 'gender' => 'male',
        'date_of_birth' => null, 'age_years' => 6, 'age_recorded_on' => '2026-11-22', 'phone' => '01711000000',
        'guardian_name' => 'Nusrat Jahan', 'guardian_relation' => 'mother', 'notes' => 'Prefers evening appointments',
    ]);
});

afterEach(fn () => Carbon::setTestNow());

it('shows who the patient is at the top', function () {
    $this->get(route('patients.show', $this->child))
        ->assertOk()
        ->assertSee('Arian Hossain · M · 6y')
        ->assertSee('FD-11-26-000002')
        ->assertSee('FD-01-23-0208')
        ->assertSee('Guardian: Nusrat Jahan (Mother)')
        ->assertSee('Prefers evening appointments')
        ->assertSee(__('Edit details'));
});

it('shows who registered the patient', function () {
    $patient = Patient::factory()->create(); // created while signed in as reception

    $this->get(route('patients.show', $patient))->assertSee('Front Desk');
});

it('lists the family on the same number, linking to each', function () {
    Patient::factory()->create(['full_name' => 'Someone Else', 'phone' => '01999999999']);

    $this->get(route('patients.show', $this->child))
        ->assertSee('Nusrat Jahan · F · 34y')
        ->assertSee(route('patients.show', $this->mother))
        ->assertDontSee('Someone Else');
});

it('says when nobody else shares the number', function () {
    $alone = Patient::factory()->create(['phone' => '01999999999']);

    $this->get(route('patients.show', $alone))->assertSee(__('No other patients share this phone.'));
});

it('opens the patient a merged record was merged into', function () {
    $this->child->forceFill(['merged_into_id' => $this->mother->id])->save();

    Volt::test('patients.show', ['patient' => $this->child])
        ->assertRedirect(route('patients.show', $this->mother));
});

it('shows only the tabs this role may open', function (RoleName $role, array $visible, array $hidden) {
    $this->actingAs(User::factory()->withRole($role)->create());

    $page = $this->get(route('patients.show', $this->mother))->assertOk();
    foreach ($visible as $tab) {
        $page->assertSee($tab);
    }
    foreach ($hidden as $tab) {
        $page->assertDontSee($tab);
    }
})->with([
    'reception' => [RoleName::Receptionist, ['Overview', 'Appointments', 'Billing'], ['Visits']],
    'doctor' => [RoleName::Doctor, ['Overview', 'Appointments', 'Visits', 'Billing'], ['Edit details']],
    'accountant' => [RoleName::Accountant, ['Overview', 'Billing'], ['Appointments', 'Visits']],
]);

it('falls back to the overview for a tab the user may not open', function () {
    Volt::test('patients.show', ['patient' => $this->mother])
        ->set('tab', 'visits')
        ->assertSee(__('Others on this number'))
        ->assertSet('tab', 'overview');
});

it('shows a placeholder until a module fills its tab', function () {
    Volt::test('patients.show', ['patient' => $this->mother])
        ->set('tab', 'appointments')
        ->assertSee('Appointments will appear here once module M04 is built.');
});

it('is not open to lab staff, who only identify patients by name and code', function () {
    $this->actingAs(User::factory()->withRole(RoleName::LabCoordinator)->create());

    $this->get(route('patients.show', $this->mother))->assertForbidden();
    $this->get(route('patients.index', ['search' => 'nusrat']))->assertDontSee(route('patients.show', $this->mother));
});

it('is linked from search results and the quick search', function () {
    $this->get(route('patients.index', ['search' => 'arian']))->assertSee(route('patients.show', $this->child));

    Volt::test('patients.quick-search')->set('search', 'arian')->assertSee(route('patients.show', $this->child));
});
