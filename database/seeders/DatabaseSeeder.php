<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * `php artisan db:seed` (or `migrate --seed`).
 *
 * Reference data needed in every environment runs first. Fictional accounts and demo
 * records run only in local/testing — production never gets made-up data.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Every environment
        $this->call(RolesAndPermissionsSeeder::class);
        Artisan::call('app:sync-sequences');

        // Development only
        if (app()->environment('local', 'testing')) {
            $this->call([
                DevUsersSeeder::class,
                DemoDataSeeder::class,
            ]);
        }
    }
}
