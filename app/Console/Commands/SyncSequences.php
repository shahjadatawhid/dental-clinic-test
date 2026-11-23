<?php

namespace App\Console\Commands;

use App\Enums\SequenceSeries;
use App\Models\NumberSequenceCounter;
use App\Services\NumberSequenceService;
use Illuminate\Console\Command;

/**
 * Creates every number series and its counter rows ahead of time (this year and next),
 * so busy moments never have two requests racing to create the same counter row.
 * Runs from the seeder and daily from the scheduler (routes/console.php).
 */
class SyncSequences extends Command
{
    protected $signature = 'app:sync-sequences';

    protected $description = 'Create number sequences and their counter rows for this year and next';

    public function handle(NumberSequenceService $service): int
    {
        $years = [(int) now()->format('Y'), (int) now()->addYear()->format('Y')];

        foreach (SequenceSeries::cases() as $series) {
            $sequence = $service->sequence($series);

            foreach (array_unique(array_map(fn ($y) => $sequence->periodFor($y), $years)) as $period) {
                NumberSequenceCounter::query()->insertOrIgnore([
                    'number_sequence_id' => $sequence->id,
                    'period' => $period,
                    'last_value' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->line(sprintf('  %-18s next: %s', $series->value, $service->preview($series)));
        }

        return self::SUCCESS;
    }
}
