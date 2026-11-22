<?php

use App\Authorization\Permissions;
use App\Enums\RoleName;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('app:sync-permissions')->assertSuccessful();
});

it('creates every permission and role template', function () {
    expect(Permission::count())->toBe(count(Permissions::all()))
        ->and(Role::pluck('name')->sort()->values()->all())
        ->toBe(collect(RoleName::cases())->pluck('value')->sort()->values()->all());
});

it('only uses permissions that exist in templates', function () {
    foreach (Permissions::roleTemplates() as $role => $permissions) {
        expect(array_diff($permissions, Permissions::all()))
            ->toBeEmpty("Role {$role} has unknown permissions");
    }
});

it('has a template for every role', function () {
    expect(array_keys(Permissions::roleTemplates()))
        ->toEqualCanonicalizing(collect(RoleName::cases())->pluck('value')->all());
});

it('is safe to run twice and keeps admin customisations', function () {
    Role::findByName(RoleName::Receptionist->value)->givePermissionTo('reports.finance');

    $this->artisan('app:sync-permissions')->assertSuccessful();

    expect(Permission::count())->toBe(count(Permissions::all()))
        ->and(Role::findByName(RoleName::Receptionist->value)->hasPermissionTo('reports.finance'))->toBeTrue();
});

it('resets roles to the template when asked', function () {
    Role::findByName(RoleName::Receptionist->value)->givePermissionTo('reports.finance');

    $this->artisan('app:sync-permissions --reset-roles')->assertSuccessful();

    expect(Role::findByName(RoleName::Receptionist->value)->hasPermissionTo('reports.finance'))->toBeFalse();
});

it('keeps clinical and financial data away from roles that should not see it', function (RoleName $role, string $permission) {
    $user = User::factory()->create()->assignRole($role->value);

    expect($user->can($permission))->toBeFalse();
})->with([
    'admin cannot see clinical notes' => [RoleName::SystemAdmin, 'visits.view'],
    'admin cannot see invoices' => [RoleName::SystemAdmin, 'invoices.view'],
    'receptionist cannot see clinical history' => [RoleName::Receptionist, 'patients.view_clinical'],
    'receptionist cannot see settlements' => [RoleName::Receptionist, 'settlements.view'],
    'doctor cannot see all settlements' => [RoleName::Doctor, 'settlements.view'],
    'doctor cannot record payments' => [RoleName::Doctor, 'payments.create'],
    'accountant cannot see visits' => [RoleName::Accountant, 'visits.view'],
    'lab coordinator cannot see full patient record' => [RoleName::LabCoordinator, 'patients.view'],
    'inventory staff cannot see patients' => [RoleName::InventoryStaff, 'patients.view'],
    'owner is read-only on payments' => [RoleName::Owner, 'payments.create'],
]);

it('gives each role the permissions its job needs', function (RoleName $role, string $permission) {
    $user = User::factory()->create()->assignRole($role->value);

    expect($user->can($permission))->toBeTrue();
})->with([
    [RoleName::SystemAdmin, 'users.manage'],
    [RoleName::Receptionist, 'appointments.create'],
    [RoleName::Receptionist, 'payments.create'],
    [RoleName::Receptionist, 'patients.view_alerts'],
    [RoleName::Doctor, 'prescriptions.create'],
    [RoleName::Doctor, 'settlements.view_own'],
    [RoleName::Accountant, 'expenses.create'],
    [RoleName::Manager, 'closing.reopen'],
    [RoleName::Owner, 'settlements.approve'],
]);
