<?php

namespace App\Services;

use App\Models\ClinicHoliday;
use App\Models\Doctor;
use App\Models\DoctorTimeOff;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * When a doctor is actually working on a date:
 *   weekly routine (M02-04) − clinic holiday − leave and blocked time (M02-05).
 *
 * M04's slot finder takes these periods and subtracts existing appointments.
 */
class DoctorAvailability
{
    /**
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable, chair_id: int|null}>
     */
    public function workingPeriods(Doctor $doctor, CarbonInterface $date): array
    {
        $day = CarbonImmutable::parse($date->toDateString());

        if (ClinicHoliday::forDate($day) !== null) {
            return [];
        }

        $periods = $doctor->hoursOn($day)->map(fn ($block) => [
            'start' => $day->setTimeFromTimeString($block->start_time),
            'end' => $day->setTimeFromTimeString($block->end_time),
            'chair_id' => $block->chair_id,
        ])->all();

        if ($periods === []) {
            return [];
        }

        $timeOff = DoctorTimeOff::where('doctor_id', $doctor->id)
            ->overlapping($day, $day->addDay())
            ->orderBy('starts_at')
            ->get();

        foreach ($timeOff as $off) {
            $periods = $this->subtract($periods, CarbonImmutable::parse($off->starts_at), CarbonImmutable::parse($off->ends_at));
        }

        return array_values($periods);
    }

    /** Is the doctor away (leave/blocked/holiday) for the whole of their routine that day? */
    public function isOffAllDay(Doctor $doctor, CarbonInterface $date): bool
    {
        return $doctor->hoursOn($date)->isNotEmpty() && $this->workingPeriods($doctor, $date) === [];
    }

    /**
     * Cut [offStart, offEnd) out of every period.
     *
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable, chair_id: int|null}>  $periods
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable, chair_id: int|null}>
     */
    private function subtract(array $periods, CarbonImmutable $offStart, CarbonImmutable $offEnd): array
    {
        $result = [];

        foreach ($periods as $p) {
            if ($offEnd <= $p['start'] || $offStart >= $p['end']) {
                $result[] = $p; // no overlap

                continue;
            }
            if ($offStart > $p['start']) {
                $result[] = ['start' => $p['start'], 'end' => $offStart, 'chair_id' => $p['chair_id']];
            }
            if ($offEnd < $p['end']) {
                $result[] = ['start' => $offEnd, 'end' => $p['end'], 'chair_id' => $p['chair_id']];
            }
        }

        return $result;
    }
}
