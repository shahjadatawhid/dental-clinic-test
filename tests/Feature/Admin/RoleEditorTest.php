<?php

use App\Authorization\Permissions;
use App\Enums\RoleName;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('app:sync-permissions');
    $this->admin = User::factory()->withRole(RoleName::SystemAdmin)->create();
    $this->receptionist = Role::findByName(RoleName::Receptionist->value);
});

it('puts every permission in exactly one screen group', function () {
    $grouped = collect(Permissions::grouped())->flatMap(fn ($perms) => array_keys($perms));

    expect($grouped->sort()->values()->all())->toBe(collect(Permissions::all())->sort()->values()->all());
});

describe('access', function () {
    it('lets an administrator open the role screens', function () {
        $this->actingAs($this->admin);

        $this->get(route('admin.roles.index'))->assertOk()->assertSee(__('Receptionist / cashier'));
        $this->get(route('admin.roles.edit', $this->receptionist))->assertOk()->assertSee('payments.create');
    });

    it('lets the owner view roles but not change them', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Owner)->create());

        $this->get(route('admin.roles.edit', $this->receptionist))->assertOk()->assertDontSee(__('Reason for change'));

        Volt::test('admin.roles.edit', ['role' => $this->receptionist])
            ->set('selected', ['reports.finance'])
            ->set('reason', 'trying to sneak in')
            ->call('save')
            ->assertForbidden();
    });

    it('forbids staff without role permissions', function (RoleName $role) {
        $this->actingAs(User::factory()->withRole($role)->create());

        $this->get(route('admin.roles.index'))->assertForbidden();
    })->with([RoleName::Manager, RoleName::Receptionist, RoleName::Doctor, RoleName::Accountant]);
});

describe('editing', function () {
    it('adds and removes permissions and audits the change with the reason', function () {
        $this->actingAs($this->admin);
        $before = $this->receptionist->permissions->pluck('name')->all();
        $after = array_values(array_diff([...$before, 'reports.finance'], ['refunds.request']));

        Volt::test('admin.roles.edit', ['role' => $this->receptionist])
            ->set('selected', $after)
            ->set('reason', 'Owner approved on 22 Nov')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.roles.index'));

        $role = $this->receptionist->fresh();
        expect($role->hasPermissionTo('reports.finance'))->toBeTrue()
            ->and($role->hasPermissionTo('refunds.request'))->toBeFalse();

        $entry = Activity::where('event', 'permissions_changed')->latest('id')->firstOrFail();
        expect($entry->properties['added'])->toBe(['reports.finance'])
            ->and($entry->properties['removed'])->toBe(['refunds.request'])
            ->and($entry->properties['reason'])->toBe('Owner approved on 22 Nov')
            ->and($entry->causer_id)->toBe($this->admin->id);
    });

    it('takes effect for users with the role immediately', function () {
        $this->actingAs($this->admin);
        $cashier = User::factory()->withRole(RoleName::Receptionist)->create();
        expect($cashier->can('reports.finance'))->toBeFalse();

        Volt::test('admin.roles.edit', ['role' => $this->receptionist])
            ->set('selected', [...$this->receptionist->permissions->pluck('name')->all(), 'reports.finance'])
            ->set('reason', 'Owner approved on 22 Nov')
            ->call('save');

        expect($cashier->fresh()->can('reports.finance'))->toBeTrue();
    });

    it('requires a reason', function () {
        $this->actingAs($this->admin);

        Volt::test('admin.roles.edit', ['role' => $this->receptionist])
            ->set('selected', ['reports.finance'])
            ->set('reason', '')
            ->call('save')
            ->assertHasErrors(['reason']);
    });

    it('rejects permissions that do not exist', function () {
        $this->actingAs($this->admin);

        Volt::test('admin.roles.edit', ['role' => $this->receptionist])
            ->set('selected', ['everything.delete'])
            ->set('reason', 'Testing invalid input')
            ->call('save')
            ->assertHasErrors(['permissions.0']);
    });

    it('does not write an audit entry when nothing changed', function () {
        $this->actingAs($this->admin);

        Volt::test('admin.roles.edit', ['role' => $this->receptionist])
            ->set('reason', 'No real change here')
            ->call('save')
            ->assertHasErrors(['permissions']);

        expect(Activity::where('event', 'permissions_changed')->count())->toBe(0);
    });

    it('never lets the admin role lose user and role management', function () {
        $this->actingAs($this->admin);
        $adminRole = Role::findByName(RoleName::SystemAdmin->value);

        Volt::test('admin.roles.edit', ['role' => $adminRole])
            ->set('selected', ['settings.view'])
            ->set('reason', 'Locking everyone out')
            ->call('save')
            ->assertHasErrors(['permissions']);

        expect($adminRole->fresh()->hasPermissionTo('roles.manage'))->toBeTrue();
    });

    it('resets the form to the template', function () {
        $this->actingAs($this->admin);
        $this->receptionist->givePermissionTo('reports.finance');

        Volt::test('admin.roles.edit', ['role' => $this->receptionist->fresh()])
            ->call('resetToTemplate')
            ->assertSet('selected', Permissions::roleTemplates()[RoleName::Receptionist->value])
            ->set('reason', 'Back to the agreed template')
            ->call('save')
            ->assertHasNoErrors();

        expect($this->receptionist->fresh()->hasPermissionTo('reports.finance'))->toBeFalse();
    });
});

it('audits role resets done from the command line', function () {
    $this->receptionist->givePermissionTo('reports.finance');

    $this->artisan('app:sync-permissions --reset-roles')->assertSuccessful();

    $entry = Activity::where('event', 'permissions_changed')->where('subject_id', $this->receptionist->id)->firstOrFail();
    expect($entry->properties['removed'])->toBe(['reports.finance'])
        ->and($entry->properties['reason'])->toContain('--reset-roles');
});
