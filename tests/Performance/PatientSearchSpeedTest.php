<?php

use App\Actions\Patients\SearchPatients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 * M03 acceptance: searching "0171" or a partial name returns matches within 1s on
 * 20,000 fictional patients. Rows are bulk-inserted (fast); the searches are the
 * same queries the patients page and the sidebar box run.
 */

const SPEED_PATIENTS = 20_000;
const SPEED_LIMIT_MS = 1000;

beforeEach(function () {
    $now = now()->toDateTimeString();
    $rows = [];

    for ($i = 1; $i <= SPEED_PATIENTS; $i++) {
        $gender = $i % 2 ? 'male' : 'female';
        $rows[] = [
            'patient_code' => sprintf('FD-%02d-%02d-%06d', ($i % 12) + 1, 24 + ($i % 3), $i),
            'legacy_code' => $i % 3 === 0 ? sprintf('FD-%02d-%02d-%04d', ($i % 12) + 1, 22, $i % 10000) : null,
            'registered_on' => now()->subDays($i % 1000)->toDateString(),
            'full_name' => fake_bd()->name($gender),
            'gender' => $gender,
            'age_years' => 5 + ($i % 70),
            'age_recorded_on' => now()->toDateString(),
            'phone' => fake_bd()->phone(),
            'address' => fake_bd()->address(),
            'preferred_language' => 'bn',
            'is_active' => $i % 30 !== 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (count($rows) === 1000) {
            DB::table('patients')->insert($rows);
            $rows = [];
        }
    }
});

it('finds patients within a second among 20,000', function (string $term) {
    expect(DB::table('patients')->count())->toBe(SPEED_PATIENTS);

    $started = hrtime(true);
    // What the page does: a page of 25 results plus the total for the pager.
    $query = SearchPatients::query($term);
    $total = (clone $query)->count();
    $page = $query->limit(25)->get();
    $ms = (hrtime(true) - $started) / 1e6;

    expect($total)->toBeGreaterThan(0, "No matches for '{$term}'")
        ->and($page)->not->toBeEmpty()
        ->and($ms)->toBeLessThan(SPEED_LIMIT_MS, sprintf("Searching '%s' took %.0f ms", $term, $ms));
})->with([
    'phone start' => '0171',
    'partial name' => 'nusr',
    'two name parts' => 'rah isl',
    'patient code' => 'FD-05-25-000004',
    'code number' => '000123',
    'old ID' => 'FD-04-22-0003',
]);
