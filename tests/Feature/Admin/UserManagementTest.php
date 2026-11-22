<?php

use App\Actions\Users\SetUserActive;
use App\Actions\Users\UpdateUser;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->artisan('app:sync-permissions');
    $this->admin = User::factory()->withRole(RoleName::SystemAdmin)->create();
});

describe('access', function () {
    it('lets an administrator open the user screens', function () {
        $this->actingAs($this->admin);

        $this->get(route('admin.users.index'))->assertOk()->assertSee($this->admin->email);
        $this->get(route('admin.users.create'))->assertOk();
        $this->get(route('admin.users.edit', $this->admin))->assertOk();
    });

    it('forbids staff without user permissions', function (RoleName $role) {
        $this->actingAs(User::factory()->withRole($role)->create());

        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.users.create'))->assertForbidden();
    })->with([RoleName::Receptionist, RoleName::Doctor, RoleName::Accountant, RoleName::Manager]);

    it('lets the owner view but not change users', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Owner)->create());

        $this->get(route('admin.users.index'))->assertOk()->assertDontSee(__('New user'));
        $this->get(route('admin.users.create'))->assertForbidden();
    });

    it('redirects guests to login', function () {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    });
});

describe('create and edit', function () {
    it('creates a user with roles', function () {
        $this->actingAs($this->admin);

        Volt::test('admin.users.form')
            ->set('name', 'Rahima Akter')
            ->set('email', 'Rahima@Clinic.test')
            ->set('phone', '01711000000')
            ->set('roles', [RoleName::Receptionist->value])
            ->set('password', 'secret-pass-123')
            ->set('password_confirmation', 'secret-pass-123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'rahima@clinic.test')->firstOrFail();
        expect($user->hasRole(RoleName::Receptionist->value))->toBeTrue()
            ->and($user->is_active)->toBeTrue()
            ->and($user->email_verified_at)->not->toBeNull()
            ->and(Hash::check('secret-pass-123', $user->password))->toBeTrue();
    });

    it('requires a role, a unique email and a confirmed password', function () {
        $this->actingAs($this->admin);

        Volt::test('admin.users.form')
            ->set('name', 'Someone')
            ->set('email', $this->admin->email)
            ->set('roles', [])
            ->set('password', 'secret-pass-123')
            ->set('password_confirmation', 'different')
            ->call('save')
            ->assertHasErrors(['email', 'roles', 'password']);
    });

    it('rejects unknown role names', function () {
        $this->actingAs($this->admin);

        Volt::test('admin.users.form')
            ->set('name', 'Someone')
            ->set('email', 'someone@clinic.test')
            ->set('roles', ['super_hacker'])
            ->set('password', 'secret-pass-123')
            ->set('password_confirmation', 'secret-pass-123')
            ->call('save')
            ->assertHasErrors(['roles.0']);
    });

    it('updates roles and keeps the password when left blank', function () {
        $this->actingAs($this->admin);
        $user = User::factory()->withRole(RoleName::Receptionist)->create();
        $oldHash = $user->password;

        Volt::test('admin.users.form', ['user' => $user])
            ->assertSet('email', $user->email)
            ->set('roles', [RoleName::Manager->value, RoleName::Receptionist->value])
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        expect($user->hasAllRoles([RoleName::Manager->value, RoleName::Receptionist->value]))->toBeTrue()
            ->and($user->password)->toBe($oldHash);
    });

    it('does not let an admin remove their own admin role', function () {
        $this->actingAs($this->admin);

        Volt::test('admin.users.form', ['user' => $this->admin])
            ->set('roles', [RoleName::Owner->value])
            ->call('save')
            ->assertHasErrors(['roles']);

        expect($this->admin->fresh()->hasRole(RoleName::SystemAdmin->value))->toBeTrue();
    });
});

describe('deactivation', function () {
    it('deactivates and reactivates a user', function () {
        $this->actingAs($this->admin);
        $user = User::factory()->withRole(RoleName::Doctor)->create();

        Volt::test('admin.users.index')->call('setActive', $user->id, false)->assertHasNoErrors();
        expect($user->fresh()->is_active)->toBeFalse()
            ->and($user->fresh()->deactivated_at)->not->toBeNull();

        Volt::test('admin.users.index')->call('setActive', $user->id, true)->assertHasNoErrors();
        expect($user->fresh()->is_active)->toBeTrue()
            ->and($user->fresh()->deactivated_at)->toBeNull();
    });

    it('does not let an admin deactivate themselves', function () {
        $this->actingAs($this->admin);

        Volt::test('admin.users.index')->call('setActive', $this->admin->id, false)->assertHasErrors(['user']);

        expect($this->admin->fresh()->is_active)->toBeTrue();
    });

    it('keeps at least one active system administrator', function () {
        // Two admins: the second may deactivate the first...
        $second = User::factory()->withRole(RoleName::SystemAdmin)->create();
        $this->actingAs($second);
        Volt::test('admin.users.index')->call('setActive', $this->admin->id, false)->assertHasNoErrors();

        // ...but now $second is the last active admin and cannot be deactivated or demoted by anyone else.
        $someoneElse = User::factory()->create();

        expect(fn () => app(SetUserActive::class)->handle($someoneElse, $second, false))
            ->toThrow(ValidationException::class);

        expect(fn () => app(UpdateUser::class)->handle($someoneElse, $second, [
            'name' => $second->name, 'email' => $second->email, 'roles' => [RoleName::Owner->value],
        ]))->toThrow(ValidationException::class);

        expect($second->fresh()->is_active)->toBeTrue()
            ->and($second->fresh()->hasRole(RoleName::SystemAdmin->value))->toBeTrue();
    });

    it('does not let a user without permission deactivate anyone', function () {
        $receptionist = User::factory()->withRole(RoleName::Receptionist)->create();
        $this->actingAs($receptionist);

        Volt::test('admin.users.index')->call('setActive', $this->admin->id, false)->assertForbidden();
    });
});

describe('login rules', function () {
    it('blocks a deactivated user from logging in', function () {
        $user = User::factory()->inactive()->create();

        Volt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    });

    it('signs out a user who is deactivated while logged in', function () {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('records the last login time', function () {
        $user = User::factory()->create();

        Volt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        expect($user->fresh()->last_login_at)->not->toBeNull();
    });

    it('has no public registration page', function () {
        $this->get('/register')->assertNotFound();
    });
});
