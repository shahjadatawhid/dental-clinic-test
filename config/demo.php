<?php

/*
 * Fictional demo data (DemoDataSeeder). Never used in production.
 */
return [

    // How many fictional patients PatientSeeder keeps in the database (tops up, never doubles).
    'patients' => (int) env('DEMO_PATIENTS', 2000),

];
