<?php

namespace App\Actions\Schedules;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Removes a routine version that hasn't started yet (e.g. entered by mistake).
 * The version before it is reopened. Versions already in force are kept for history.
 */
class RemoveUpcomingSchedule
{
    public function handle(DoctorSchedule $schedule): void
    {
        if ($schedule->hasStarted()) {
            throw ValidationException::withMessages([
                'schedule' => __('This routine has already started and is kept for history. Save a new routine from a later date instead.'),
            ]);
        }

        DB::transaction(function () use ($schedule) {
            Doctor::whereKey($schedule->doctor_id)->lockForUpdate()->first();

            $previous = DoctorSchedule::where('doctor_id', $schedule->doctor_id)
                ->where('effective_from', '<', $schedule->effective_from)
                ->orderByDesc('effective_from')
                ->first();

            // Nothing may follow a removed version, otherwise the previous one would overlap it.
            $next = DoctorSchedule::where('doctor_id', $schedule->doctor_id)
                ->where('effective_from', '>', $schedule->effective_from)
                ->orderBy('effective_from')
                ->first();

            $previous?->update(['effective_to' => $next?->effective_from->copy()->subDay()]);

            Audit::record('removed', $schedule, 'Upcoming routine removed', [
                'doctor_id' => $schedule->doctor_id,
                'effective_from' => $schedule->effective_from->toDateString(),
            ]);

            $schedule->delete();
        });
    }
}
