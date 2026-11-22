<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateUser
{
    /**
     * @param  array{name?: string, email?: string, phone?: string|null, password?: string, password_confirmation?: string, roles?: list<string>}  $input
     */
    public function handle(array $input): User
    {
        $data = Validator::make($input, [
            ...UserRules::profile(),
            'password' => UserRules::password(required: true),
            'roles' => UserRules::roles(),
            'roles.*' => UserRules::role(),
        ])->validate();

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
            ]);

            // Accounts created by an administrator are trusted; no email verification step.
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles($data['roles']);

            Audit::record('roles_changed', $user, 'Roles assigned', [
                'old' => [],
                'new' => $user->getRoleNames()->sort()->values()->all(),
            ]);

            return $user;
        });
    }
}
