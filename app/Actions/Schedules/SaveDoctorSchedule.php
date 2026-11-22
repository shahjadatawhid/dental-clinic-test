<?php

namespace App\Actions\Schedules;

use App\Models\Chair;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Support\Week;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Saves a doctor's weekly routine from a date onwards.
 *
 *  - Same start date as an existing version → that version's blocks are replaced.
 *  - Later than the newest version → the newest version ends the day before, a new one starts.
 *  - Earlier than the newest version → refused (edit or remove the newer version instead),
 *    so versions never overlap.
 */
class SaveDoctorSchedule
{
    /**
     * @param  list<array{weekday: int|string, start_time: string, end_time: string, chair_id?: int|string|null}>  $blocks
     */
    public function handle(Doctor $doctor, string $effectiveFrom, array $blocks, ?string $note = null): DoctorSchedule
    {
        $data = Validator::make(
            ['effective_from' => $effectiveFrom, 'blocks' => $blocks, 'note' => $note],
            [
                'effective_from' => ['required', 'date'],
                'note' => ['nullable', 'string', 'max:255'],
                'blocks' => ['array'],
                'blocks.*.weekday' => ['required', 'integer', 'between:0,6'],
                'blocks.*.start_time' => ['required', 'date_format:H:i'],
                'blocks.*.end_time' => ['required', 'date_format:H:i'],
                'blocks.*.chair_id' => ['nullable', 'integer', 'exists:chairs,id'],
            ],
            [],
            ['blocks.*.start_time' => __('start time'), 'blocks.*.end_time' => __('end time')],
        )->validate();

        $blocks = collect($data['blocks'])->map(fn (array $b) => [
            'weekday' => (int) $b['weekday'],
            'start_time' => $b['start_time'].':00',
            'end_time' => $b['end_time'].':00',
            'chair_id' => filled($b['chair_id'] ?? null) ? (int) $b['chair_id'] : null,
        ]);

        $this->assertBlocksMakeSense($blocks->all());

        $from = Carbon::parse($data['effective_from'])->startOfDay();

        return DB::transaction(function () use ($doctor, $from, $blocks, $data) {
            // Lock the doctor row so two people editing at once can't create overlapping
            // versions (locking the version rows alone does nothing when there are none yet).
            Doctor::whereKey($doctor->id)->lockForUpdate()->first();
            $versions = DoctorSchedule::where('doctor_id', $doctor->id)->orderByDesc('effective_from')->get();
            $newest = $versions->first();

            $schedule = $versions->first(fn (DoctorSchedule $s) => $s->effective_from->equalTo($from));

            if ($schedule === null && $newest !== null && $from->lt($newest->effective_from)) {
                throw ValidationException::withMessages([
                    'effective_from' => __('A routine starting :date already exists. Choose a later date, or edit that routine.', [
                        'date' => $newest->effective_from->format('d M Y'),
                    ]),
                ]);
            }

            if ($schedule === null) {
                if ($newest !== null) {
                    $newest->update(['effective_to' => $from->copy()->subDay()]);
                }
                $schedule = DoctorSchedule::create([
                    'doctor_id' => $doctor->id,
                    'effective_from' => $from,
                    'note' => $data['note'] ?? null,
                ]);
            } else {
                $schedule->update(['note' => $data['note'] ?? null]);
                $schedule->blocks()->delete();
            }

            $schedule->blocks()->createMany($blocks->all());

            return $schedule->load('blocks');
        });
    }

    /**
     * @param  list<array{weekday: int, start_time: string, end_time: string, chair_id: int|null}>  $blocks
     */
    private function assertBlocksMakeSense(array $blocks): void
    {
        $errors = [];

        foreach ($blocks as $i => $block) {
            if ($block['end_time'] <= $block['start_time']) {
                $errors["blocks.{$i}.end_time"] = __('End time must be after start time.');
            }
            if ($block['chair_id'] !== null && ! Chair::usable()->whereKey($block['chair_id'])->exists()) {
                $errors["blocks.{$i}.chair_id"] = __('This chair (or its room) is inactive.');
            }
        }

        // Blocks on the same day must not overlap.
        foreach (collect($blocks)->groupBy('weekday') as $weekday => $dayBlocks) {
            $sorted = $dayBlocks->sortBy('start_time')->values();
            for ($j = 1; $j < $sorted->count(); $j++) {
                if ($sorted[$j]['start_time'] < $sorted[$j - 1]['end_time']) {
                    $index = array_search($sorted[$j], $blocks, true);
                    $errors["blocks.{$index}.start_time"] = __(':day has overlapping times.', ['day' => Week::name((int) $weekday)]);
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
