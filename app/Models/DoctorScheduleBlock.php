<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A working block: weekday + start/end time (+ optional chair).
 *
 * @property int $id
 * @property int $doctor_schedule_id
 * @property int $weekday 0 = Sunday … 6 = Saturday
 * @property string $start_time "HH:MM:SS"
 * @property string $end_time "HH:MM:SS"
 * @property int|null $chair_id
 * @property-read Chair|null $chair
 */
class DoctorScheduleBlock extends Model
{
    use Auditable;

    protected $fillable = ['doctor_schedule_id', 'weekday', 'start_time', 'end_time', 'chair_id'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'chair_id' => 'integer'];
    }

    /** @return BelongsTo<DoctorSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DoctorSchedule::class, 'doctor_schedule_id');
    }

    /** @return BelongsTo<Chair, $this> */
    public function chair(): BelongsTo
    {
        return $this->belongsTo(Chair::class);
    }

    /** "10:00–13:00" */
    public function timeRange(): string
    {
        return substr($this->start_time, 0, 5).'–'.substr($this->end_time, 0, 5);
    }
}
