<?php

use App\Actions\Patients\SearchPatients;
use App\Enums\RoleName;
use App\Models\Patient;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->artisan('app:sync-permissions');

    $this->nusrat = Patient::factory()->create([
        'patient_code' => 'FD-11-26-000001', 'full_name' => 'Nusrat Jahan', 'phone' => '01711000000',
        'gender' => 'female', 'date_of_birth' => '1992-01-15',
    ]);
    $this->arian = Patient::factory()->create([
        'patient_code' => 'FD-11-26-000002', 'full_name' => 'Arian Hossain', 'phone' => '01711000000', // same family number
    ]);
    $this->rakib = Patient::factory()->withLegacyCode('FD-01-23-0208')->create([
        'patient_code' => 'FD-10-26-000012', 'full_name' => 'Md. Rakib Hossain', 'phone' => '01811222333', 'alt_phone' => '01911444555',
    ]);
});

/** @return list<string> patient codes found */
function found(string $term, bool $byPhone = true, string $status = 'active'): array
{
    return SearchPatients::query($term, $byPhone, $status)->pluck('patient_code')->all();
}

describe('search', function () {
    it('finds by name words in any order and case', function () {
        expect(found('nusrat'))->toBe(['FD-11-26-000001'])
            ->and(found('jahan NUS'))->toBe(['FD-11-26-000001'])
            ->and(found('hossain'))->toBe(['FD-11-26-000002', 'FD-10-26-000012'])
            ->and(found('rakib hossain'))->toBe(['FD-10-26-000012'])
            ->and(found('nusrat rakib'))->toBe([]);
    });

    it('finds by phone however it is typed, listing everyone on a family number', function (string $typed) {
        expect(found($typed))->toEqualCanonicalizing(['FD-11-26-000001', 'FD-11-26-000002']);
    })->with(['01711000000', '+880 1711-000000', '8801711000000', '0171', '1711 000']);

    it('finds by second phone', function () {
        expect(found('01911444555'))->toBe(['FD-10-26-000012']);
    });

    it('finds by patient code, in full or in part, and by old code', function () {
        expect(found('FD-11-26-000002'))->toBe(['FD-11-26-000002'])
            ->and(found('fd-10-26'))->toBe(['FD-10-26-000012'])
            ->and(found('000012'))->toBe(['FD-10-26-000012'])
            ->and(found('FD-01-23-0208'))->toBe(['FD-10-26-000012'])
            ->and(found(' fd-01-23-0208 '))->toBe(['FD-10-26-000012']);
    });

    it('puts an exact code match first', function () {
        Patient::factory()->create(['patient_code' => 'FD-11-26-0000012', 'full_name' => 'Aaron Ahmed']);

        expect(found('FD-10-26-000012')[0])->toBe('FD-10-26-000012');
    });

    it('treats % and _ literally', function () {
        expect(found('%'))->toBe([])->and(found('_'))->toBe([]);
    });

    it('shows active patients by default, others on request', function () {
        $inactive = Patient::factory()->inactive()->create(['full_name' => 'Nusrat Inactive']);
        $merged = Patient::factory()->create(['full_name' => 'Nusrat Merged']);
        $merged->forceFill(['merged_into_id' => $this->nusrat->id])->save();

        expect(found('nusrat'))->toBe(['FD-11-26-000001'])
            ->and(found('nusrat', status: 'inactive'))->toBe([$inactive->patient_code])
            ->and(found('nusrat', status: 'all'))->toHaveCount(3);
    });

    it('lists the newest registrations when nothing is typed', function () {
        $this->nusrat->forceFill(['registered_on' => '2026-11-20'])->save();
        $this->arian->forceFill(['registered_on' => '2026-11-21'])->save();
        $this->rakib->forceFill(['registered_on' => '2026-10-01'])->save();

        expect(found(''))->toBe(['FD-11-26-000002', 'FD-11-26-000001', 'FD-10-26-000012']);
    });

    it('does not search phones for users who may only see name and code', function () {
        expect(found('01711000000', byPhone: false))->toBe([])
            ->and(found('nusrat', byPhone: false))->toBe(['FD-11-26-000001']);
    });
});

describe('patients page', function () {
    it('lets reception search and see phones', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Receptionist)->create());

        $this->get(route('patients.index', ['search' => '0171']))
            ->assertOk()
            ->assertSee('Nusrat Jahan · F · 34y')
            ->assertSee('FD-11-26-000002')
            ->assertSee('01711000000')
            ->assertDontSee('Rakib');
    });

    it('shows the old code under the new one', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Receptionist)->create());

        Volt::test('patients.index', ['search' => 'rakib'])
            ->assertSee('FD-10-26-000012')
            ->assertSee('FD-01-23-0208');
    });

    it('shows lab staff only names and codes', function () {
        $this->actingAs(User::factory()->withRole(RoleName::LabCoordinator)->create());

        $this->get(route('patients.index', ['search' => 'nusrat']))
            ->assertOk()
            ->assertSee('FD-11-26-000001')
            ->assertDontSee('01711000000');

        Volt::test('patients.index')->set('search', '01711000000')->assertSee('No patients found.');
    });

    it('is in the menu for those who can search patients', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Receptionist)->create());
        $this->get(route('dashboard'))->assertSee(route('patients.index'));

        $this->actingAs(User::factory()->withRole(RoleName::InventoryStaff)->create());
        $this->get(route('dashboard'))->assertDontSee(route('patients.index'))->assertDontSee(__('Find patient'));
    });
});

describe('top-bar search', function () {
    beforeEach(fn () => $this->actingAs(User::factory()->withRole(RoleName::Receptionist)->create()));

    it('is on every page for reception', function () {
        $this->get(route('dashboard'))->assertSee(__('Find patient'));
    });

    it('shows the first matches as you type', function () {
        Volt::test('patients.quick-search')
            ->set('search', 'hossain')
            ->assertSee('Arian Hossain')
            ->assertSee('Md. Rakib Hossain')
            ->assertSee('FD-10-26-000012')
            ->assertDontSee('Nusrat')
            ->assertDontSee(__('See all matches'));
    });

    it('offers the full list when there are more matches', function () {
        Patient::factory()->count(9)->create(['full_name' => 'Rahim Uddin']);

        Volt::test('patients.quick-search')->set('search', 'rahim')->assertSee(__('See all matches'));
    });

    it('opens the full search page on Enter', function () {
        Volt::test('patients.quick-search')
            ->set('search', ' 0171 ')
            ->call('openFullSearch')
            ->assertRedirect(route('patients.index', ['search' => '0171']));
    });

    it('refuses users without patient access', function () {
        $this->actingAs(User::factory()->withRole(RoleName::InventoryStaff)->create());

        Volt::test('patients.quick-search')->set('search', 'nusrat')->assertDontSee('Nusrat')
            ->call('openFullSearch')->assertForbidden();
    });
});
