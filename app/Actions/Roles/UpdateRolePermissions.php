<?php

namespace App\Actions\Roles;

use App\Authorization\Permissions;
use App\Enums\RoleName;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Changes which permissions a role has. Every change needs a reason and is audited
 * with the permissions added and removed.
 */
class UpdateRolePermissions
{
    /**
     * @param  list<string>  $permissions
     * @return array{added: list<string>, removed: list<string>}
     */
    public function handle(Role $role, array $permissions, ?string $reason): array
    {
        $data = Validator::make(
            ['permissions' => $permissions, 'reason' => $reason],
            [
                'permissions' => ['array'],
                'permissions.*' => ['string', 'distinct', Rule::in(Permissions::all())],
                'reason' => ['required', 'string', 'min:5', 'max:500'],
            ],
            ['reason.required' => __('Please give a reason for this change.')],
        )->validate();

        $new = collect($data['permissions'])->unique()->sort()->values();

        if ($role->name === RoleName::SystemAdmin->value) {
            $missing = array_diff(Permissions::ADMIN_REQUIRED, $new->all());
            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'permissions' => __('The system administrator role must keep: :list', ['list' => implode(', ', $missing)]),
                ]);
            }
        }

        $old = $role->permissions()->pluck('name')->sort()->values();
        $added = $new->diff($old)->values()->all();
        $removed = $old->diff($new)->values()->all();

        if ($added === [] && $removed === []) {
            return ['added' => [], 'removed' => []];
        }

        DB::transaction(function () use ($role, $new, $added, $removed, $data) {
            $role->syncPermissions($new->all());

            Audit::record('permissions_changed', $role, "Permissions changed for role {$role->name}", [
                'role' => $role->name,
                'added' => $added,
                'removed' => $removed,
            ], reason: $data['reason']);
        });

        return ['added' => $added, 'removed' => $removed];
    }
}
