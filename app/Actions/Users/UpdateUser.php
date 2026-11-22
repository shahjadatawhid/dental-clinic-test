<?php

namespace App\Actions\Users;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    /**
     * @param  array{name?: string, email?: string, phone?: string|null, password?: string|null, password_confirmation?: string|null, roles?: list<string>}  $input
     */
    public function handle(User $actor, User $user, array $input): User
    {
        $data = Validator::make($input, [
            ...UserRules::profile($user),
            'password' => UserRules::password(required: false),
            'roles' => UserRules::roles(),
            'roles.*' => UserRules::role(),
        ])->validate();

        $admin = RoleName::SystemAdmin->value;
        $removingAdmin = $user->hasRole($admin) && ! in_array($admin, $data['roles'], true);

        if ($removingAdmin && $actor->is($user)) {
            throw ValidationException::withMessages([
                'roles' => __('You cannot remove the system administrator role from your own account.'),
            ]);
        }

        if ($removingAdmin && $user->is_active && UserGuards::activeAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'roles' => __('At least one active system administrator is required.'),
            ]);
        }

        return DB::transaction(function () use ($user, $data) {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);

            $passwordChanged = ! empty($data['password']);
            if ($passwordChanged) {
                $user->password = $data['password'];
            }

            $user->save();

            if ($passwordChanged) {
                Audit::record('password_reset', $user, 'Password reset by administrator');
            }

            $oldRoles = $user->getRoleNames()->sort()->values()->all();
            $user->syncRoles($data['roles']);
            $newRoles = $user->getRoleNames()->sort()->values()->all();

            if ($oldRoles !== $newRoles) {
                Audit::record('roles_changed', $user, 'Roles changed', ['old' => $oldRoles, 'new' => $newRoles]);
            }

            return $user;
        });
    }
}
