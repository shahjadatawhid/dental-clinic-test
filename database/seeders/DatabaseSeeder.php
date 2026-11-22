<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // Fictional login accounts for local development only.
        // Every account uses the password "password".
        if (app()->environment('local', 'testing')) {
            foreach (RoleName::cases() as $role) {
                $user = User::firstOrCreate(
                    ['email' => str_replace('_', '-', $role->value).'@clinic.test'],
                    ['name' => $role->label(), 'password' => 'password', 'email_verified_at' => now()],
                );
                $user->syncRoles([$role->value]);
            }
        }
    }
}
