<?php

namespace App\Console\Commands;

use App\Authorization\Permissions;
use App\Enums\RoleName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'app:sync-permissions
                            {--reset-roles : Overwrite every role\'s permissions with the template (removes admin customisations)}';

    protected $description = 'Create missing permissions and roles from App\Authorization\Permissions';

    public function handle(PermissionRegistrar $registrar): int
    {
        $registrar->forgetCachedPermissions();
        $guard = config('auth.defaults.guard');

        DB::transaction(function () use ($guard) {
            foreach (Permissions::all() as $name) {
                Permission::findOrCreate($name, $guard);
            }

            foreach (Permissions::roleTemplates() as $roleName => $permissions) {
                $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();

                if ($role === null) {
                    Role::create(['name' => $roleName, 'guard_name' => $guard])->syncPermissions($permissions);
                    $this->line("  created role <info>{$roleName}</info> with ".count($permissions).' permissions');
                } elseif ($this->option('reset-roles')) {
                    $role->syncPermissions($permissions);
                    $this->line("  reset role <comment>{$roleName}</comment> to template");
                }
            }
        });

        $unknown = Permission::whereNotIn('name', Permissions::all())->pluck('name');
        if ($unknown->isNotEmpty()) {
            $this->warn('Permissions in the database but not in code (not deleted): '.$unknown->implode(', '));
        }

        $registrar->forgetCachedPermissions();
        $this->info(count(Permissions::all()).' permissions and '.count(RoleName::cases()).' roles are in sync.');

        return self::SUCCESS;
    }
}
