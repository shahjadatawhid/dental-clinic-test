<?php

namespace App\Models;

use App\Enums\TimeOffType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $doctor_id
 * @property TimeOffType $type
 * @property Carbon $starts_at
 * @property Carbon $ends_at exclusive
 * @property string|null $reason
 * @property-read Doctor $doctor
 */
class DoctorTimeOff extends Model
{
    use Auditable, HasUserstamps;

    protected $table = 'doctor_time_off';

    protected $fillable = ['doctor_id', 'type', 'starts_at', 'ends_at', 'reason'];

    protected function casts(): array
    {
        return [
            'type' => TimeOffType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Entries that overlap the period [from, to).
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function isAllDay(): bool
    {
        return $this->starts_at->isStartOfDay() && $this->ends_at->isStartOfDay();
    }

    /** "3 Dec – 5 Dec 2026" or "Thu 4 Dec, 18:00–19:00" */
    public function describe(): string
    {
        if ($this->isAllDay()) {
            $last = $this->ends_at->copy()->subDay();

            return $last->isSameDay($this->starts_at)
                ? $this->starts_at->format('D j M Y')
                : $this->starts_at->format('j M').' – '.$last->format('j M Y');
        }

        return $this->starts_at->format('D j M Y, H:i').'–'.$this->ends_at->format('H:i');
    }
}
