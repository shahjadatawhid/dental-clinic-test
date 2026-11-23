<?php

use App\Authorization\AuditLogAccess;
use App\Authorization\Permissions;
use App\Enums\RoleName;
use App\Models\User;
use App\Settings\Settings;
use Livewire\Volt\Volt;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->artisan('app:sync-permissions');
    $this->admin = User::factory()->withRole(RoleName::SystemAdmin)->create(['name' => 'Admin Rahman']);
});

describe('access', function () {
    it('is open to roles with audit.view', function (RoleName $role) {
        $this->actingAs(User::factory()->withRole($role)->create());

        $this->get(route('admin.audit.index'))->assertOk();
    })->with([RoleName::SystemAdmin, RoleName::Owner, RoleName::Manager, RoleName::Accountant]);

    it('is closed to everyone else', function (RoleName $role) {
        $this->actingAs(User::factory()->withRole($role)->create());

        $this->get(route('admin.audit.index'))->assertForbidden();
    })->with([RoleName::Receptionist, RoleName::Doctor, RoleName::LabCoordinator, RoleName::InventoryStaff]);
});

describe('listing', function () {
    it('shows who did what with old and new values', function () {
        $this->actingAs($this->admin);
        app(Settings::class)->setMany(['security.idle_timeout_minutes' => 45]);
        app(Settings::class)->setMany(['security.idle_timeout_minutes' => 60]);

        Volt::test('admin.audit.index')
            ->assertSee('Admin Rahman')
            ->assertSee('security.idle_timeout_minutes')
            ->assertSee('45')
            ->assertSee('60');
    });

    it('filters by area, action, user, date and text', function () {
        $other = User::factory()->create(['name' => 'Other Person']);
        $this->actingAs($this->admin);
        app(Settings::class)->setMany(['clinic.name' => 'Smile Care']);
        activity('auth')->event('login')->causedBy($other)->performedOn($other)->log('Logged in');

        Volt::test('admin.audit.index')
            ->set('log', 'auth')
            ->assertSee('Logged in')->assertDontSee('Smile Care')
            ->set('log', '')
            ->set('user', (string) $this->admin->id)
            ->assertSee('Smile Care')->assertDontSee('Logged in')
            ->set('user', '')
            ->set('search', 'Smile')
            ->assertSee('Smile Care')->assertDontSee('Logged in')
            ->set('search', '')
            ->set('from', now()->addDay()->toDateString())
            ->assertSee(__('No audit entries match these filters.'));
    });

    it('shows the history of one record from the user list link', function () {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $this->actingAs($this->admin);
        $alice->update(['phone' => '01711111111']);
        $bob->update(['phone' => '01822222222']);

        $this->get(route('admin.audit.index', ['subjectType' => $alice->getMorphClass(), 'subjectId' => $alice->id]))
            ->assertOk()
            ->assertSee('01711111111')
            ->assertDontSee('01822222222');
    });
});

describe('privacy', function () {
    it('hides clinical logs from roles that may not see clinical records', function () {
        // A fake clinical entry, as M05 will produce for visits.
        activity('visits')->event('updated')->withProperties(['diagnosis' => 'Secret clinical note'])->log('Visit updated');
        activity('settings')->event('updated')->log('Setting changed');

        $accountant = User::factory()->withRole(RoleName::Accountant)->create();
        $manager = User::factory()->withRole(RoleName::Manager)->create();

        expect(AuditLogAccess::hiddenFor($accountant))->toContain('visits')
            ->and(AuditLogAccess::hiddenFor($manager))->not->toContain('visits');

        $this->actingAs($accountant);
        Volt::test('admin.audit.index')->assertSee('Setting changed')->assertDontSee('Secret clinical note');

        $this->actingAs($manager);
        Volt::test('admin.audit.index')->assertSee('Secret clinical note');
    });

    it('only restricts logs with permissions that exist', function () {
        expect(array_diff(array_values(AuditLogAccess::RESTRICTED), Permissions::all()))->toBeEmpty();
    });
});

it('offers no way to change or delete entries', function () {
    $this->actingAs($this->admin);
    activity('settings')->log('Something');

    $component = Volt::test('admin.audit.index');
    foreach (['delete', 'destroy', 'update', 'save'] as $method) {
        expect(method_exists($component->instance(), $method))->toBeFalse();
    }
    expect(Activity::count())->toBeGreaterThan(0);
});
