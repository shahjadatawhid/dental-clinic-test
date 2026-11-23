<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Fictional clinic data so every screen can be tried end to end:
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Each module adds its own seeder here as it is built, in dependency order, e.g.
 *   M02 StaffSeeder (doctors, rooms, chairs, working hours)
 *   M03 PatientSeeder (2,000+ patients, families sharing phones; DEMO_PATIENTS)
 *   M04 AppointmentSeeder ...
 *
 * Rules: fictional data only (factories + fake_bd()->name() etc.), safe to run twice,
 * never in production.
 */
class DemoDataSeeder extends Seeder
{
    use GuardsAgainstProduction;

    public function run(): void
    {
        $this->refuseInProduction();

        $this->call([
            StaffSeeder::class,          // M02-06
            PatientSeeder::class,        // M03-07
        ]);
    }
}
