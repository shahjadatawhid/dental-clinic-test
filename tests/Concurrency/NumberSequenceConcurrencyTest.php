<?php

/*
 * Real concurrency: several PHP processes take numbers at the same moment from the
 * test database. This folder does NOT use RefreshDatabase (the workers can't see an
 * open test transaction), so the test cleans up its own rows.
 */

use App\Enums\SequenceSeries;
use App\Models\NumberSequence;
use App\Models\NumberSequenceCounter;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

function cleanSequenceTables(): void
{
    NumberSequenceCounter::query()->delete();
    NumberSequence::query()->delete();
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    cleanSequenceTables();
});

afterEach(fn () => cleanSequenceTables());

it('never gives the same number to two simultaneous requests', function (bool $precreated) {
    $workers = 4;
    $perWorker = 25;
    $series = SequenceSeries::ReceiptVoucher;

    if ($precreated) {
        $this->artisan('app:sync-sequences')->assertSuccessful();
    }

    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => config('database.default'),
        'DB_DATABASE' => config('database.connections.'.config('database.default').'.database'),
    ];

    $results = Process::pool(function (Pool $pool) use ($workers, $perWorker, $series, $env) {
        foreach (range(1, $workers) as $i) {
            $pool->path(base_path())->env($env)->timeout(120)
                ->command([PHP_BINARY, 'tests/Support/sequence-worker.php', $series->value, (string) $perWorker]);
        }
    })->start()->wait();

    $numbers = collect();
    foreach ($results->collect() as $result) {
        expect($result->successful())->toBeTrue($result->errorOutput());
        $numbers = $numbers->merge(array_filter(explode(PHP_EOL, trim($result->output()))));
    }

    $year = now()->format('Y');
    $expected = collect(range(1, $workers * $perWorker))
        ->map(fn ($n) => sprintf('RV-%s-%06d', $year, $n));

    expect($numbers)->toHaveCount($workers * $perWorker)
        ->and($numbers->duplicates())->toBeEmpty()
        ->and($numbers->sort()->values()->all())->toBe($expected->all());
})->with([
    'counters created in advance (normal day)' => [true],
    'first use of the series (no rows yet)' => [false],
])->group('concurrency');
