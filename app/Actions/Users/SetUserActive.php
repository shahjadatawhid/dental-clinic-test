<?php

namespace App\Actions\Users;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deactivate or reactivate a user. Users are never deleted, so their name
 * stays on every appointment, visit, invoice and voucher they recorded.
 */
class SetUserActive
{
    public function handle(User $actor, User $user, bool $active): User
    {
        if ($user->is_active === $active) {
            return $user;
        }

        if (! $active && $actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => __('You cannot deactivate your own account.'),
            ]);
        }

        if (! $active && $user->hasRole(RoleName::SystemAdmin->value) && UserGuards::activeAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'user' => __('At least one active system administrator is required.'),
            ]);
        }

        DB::transaction(function () use ($user, $active) {
            $user->forceFill([
                'is_active' => $active,
                'deactivated_at' => $active ? null : now(),
                // Invalidate "remember me" so the user can't come back with an old cookie.
                'remember_token' => $active ? $user->remember_token : null,
            ])->save();

            Audit::record($active ? 'reactivated' : 'deactivated', $user, $active ? 'User reactivated' : 'User deactivated');

            if (! $active) {
                // End the user's open sessions (database session driver).
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        });

        return $user;
    }
}
