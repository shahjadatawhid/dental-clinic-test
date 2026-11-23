<?php

namespace App\Actions\Schedules;

use App\Models\ClinicHoliday;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Adds one holiday per day from from_date to to_date (inclusive).
 * Days that are already holidays keep their existing name.
 */
class AddClinicHolidays
{
    /**
     * @param  array{name?: string, from_date?: string, to_date?: string|null}  $input
     * @return int number of days added
     */
    public function handle(array $input): int
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'from_date' => ['required', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ], [], ['from_date' => __('date'), 'to_date' => __('last day')])->validate();

        $from = Carbon::parse($data['from_date']);
        $to = Carbon::parse($data['to_date'] ?? $data['from_date']);

        if ($from->diffInDays($to) > 31) {
            throw ValidationException::withMessages([
                'to_date' => __('A holiday can be at most a month long. Add longer closures in parts.'),
            ]);
        }

        return DB::transaction(function () use ($from, $to, $data) {
            $added = 0;
            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                $holiday = ClinicHoliday::firstOrCreate(['date' => $day->toDateString()], ['name' => trim($data['name'])]);
                $added += $holiday->wasRecentlyCreated ? 1 : 0;
            }

            return $added;
        });
    }
}
