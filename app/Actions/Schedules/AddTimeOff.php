<?php

namespace App\Actions\Schedules;

use App\Enums\TimeOffType;
use App\Models\Doctor;
use App\Models\DoctorTimeOff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Records leave (whole days, from_date → to_date inclusive) or blocked time
 * (one date, start_time → end_time) for a doctor.
 *
 * Existing appointments in the period are NOT moved automatically; once M04 exists the
 * screen lists them so reception can reschedule (see docs/modules/M02-staff-schedules.md).
 */
class AddTimeOff
{
    /**
     * @param  array{type?: string, from_date?: string, to_date?: string|null, start_time?: string|null, end_time?: string|null, reason?: string|null}  $input
     */
    public function handle(Doctor $doctor, array $input): DoctorTimeOff
    {
        $isLeave = ($input['type'] ?? null) === TimeOffType::Leave->value;

        $data = Validator::make($input, [
            'type' => ['required', 'in:leave,blocked'],
            'from_date' => ['required', 'date'],
            'to_date' => [$isLeave ? 'nullable' : 'prohibited', 'date', 'after_or_equal:from_date'],
            'start_time' => [$isLeave ? 'prohibited' : 'required', 'date_format:H:i'],
            'end_time' => [$isLeave ? 'prohibited' : 'required', 'date_format:H:i', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], [
            'from_date' => __('date'), 'to_date' => __('last day'),
        ])->validate();

        $from = Carbon::parse($data['from_date'])->startOfDay();

        [$startsAt, $endsAt] = $isLeave
            ? [$from, Carbon::parse($data['to_date'] ?? $data['from_date'])->startOfDay()->addDay()]
            : [$from->copy()->setTimeFromTimeString($data['start_time']), $from->copy()->setTimeFromTimeString($data['end_time'])];

        return DoctorTimeOff::create([
            'doctor_id' => $doctor->id,
            'type' => $data['type'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => ($data['reason'] ?? '') !== '' ? $data['reason'] : null,
        ]);
    }
}
