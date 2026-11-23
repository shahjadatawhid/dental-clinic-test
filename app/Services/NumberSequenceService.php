<?php

namespace App\Services;

use App\Enums\SequenceSeries;
use App\Models\NumberSequence;
use App\Models\NumberSequenceCounter;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Hands out document numbers (FD-000001, RV-2026-000001, ...).
 *
 *     $no = app(NumberSequenceService::class)->next(SequenceSeries::ReceiptVoucher, $receiptDate);
 *
 * Call it INSIDE the transaction that saves the document. The counter row stays locked
 * until that transaction commits, so two cashiers can never get the same number, and if
 * saving fails the number is released with the rollback (no gaps from failed saves).
 * A number that was saved and later voided stays used — voided documents keep their number.
 */
class NumberSequenceService
{
    public function next(SequenceSeries $series, ?CarbonInterface $date = null): string
    {
        $date ??= now();
        $year = (int) $date->format('Y');

        return DB::transaction(function () use ($series, $year, $date) {
            $sequence = $this->sequence($series);
            $period = $sequence->periodFor($year);

            // Lock first. Only when the row doesn't exist yet (first number of the series or
            // of a new year) create it — inserting on every call would take shared locks that
            // can deadlock with FOR UPDATE under concurrent requests.
            $counter = $this->lockedCounter($sequence->id, $period);

            if ($counter === null) {
                NumberSequenceCounter::query()->insertOrIgnore([
                    'number_sequence_id' => $sequence->id,
                    'period' => $period,
                    'last_value' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $counter = $this->lockedCounter($sequence->id, $period) ?? throw new \RuntimeException('Sequence counter missing.');
            }

            $counter->last_value++;
            $counter->save();

            return $sequence->formatNumber($counter->last_value, $date);
        });
    }

    /**
     * What the next number would look like, without taking it. For display only —
     * never save this value; another user may take it first.
     */
    public function preview(SequenceSeries $series, ?CarbonInterface $date = null): string
    {
        $date ??= now();
        $sequence = $this->sequence($series);

        $last = (int) NumberSequenceCounter::query()
            ->where('number_sequence_id', $sequence->id)
            ->where('period', $sequence->periodFor((int) $date->format('Y')))
            ->value('last_value');

        return $sequence->formatNumber($last + 1, $date);
    }

    private function lockedCounter(int $sequenceId, int $period): ?NumberSequenceCounter
    {
        return NumberSequenceCounter::query()
            ->where('number_sequence_id', $sequenceId)
            ->where('period', $period)
            ->lockForUpdate()
            ->first();
    }

    public function sequence(SequenceSeries $series): NumberSequence
    {
        $sequence = NumberSequence::query()->where('series', $series->value)->first();

        if ($sequence !== null) {
            return $sequence;
        }

        // First use of this series: create it from the defaults (safe if two requests race).
        NumberSequence::query()->insertOrIgnore([
            'series' => $series->value,
            ...$series->defaults(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // A locking read sees rows committed by other requests after our transaction began;
        // a plain read would use MySQL's REPEATABLE READ snapshot and could miss the row
        // that another request inserted a moment ago.
        return NumberSequence::query()->where('series', $series->value)->sharedLock()->firstOrFail();
    }
}
