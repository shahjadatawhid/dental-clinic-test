<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One fictional login per role, e.g. receptionist@clinic.test / password.
 * Local and testing only.
 */
class DevUsersSeeder extends Seeder
{
    use GuardsAgainstProduction;

    public const PASSWORD = 'password';

    public function run(): void
    {
        $this->refuseInProduction();

        foreach (RoleName::cases() as $role) {
            $user = User::firstOrCreate(
                ['email' => self::emailFor($role)],
                ['name' => $role->label(), 'password' => self::PASSWORD, 'email_verified_at' => now()],
            );
            $user->syncRoles([$role->value]);
        }
    }

    public static function emailFor(RoleName $role): string
    {
        return str_replace('_', '-', $role->value).'@clinic.test';
    }
}
