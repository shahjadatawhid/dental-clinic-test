<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One version of a doctor's weekly routine. Change it only through
 * App\Actions\Schedules\SaveDoctorSchedule so versions never overlap.
 *
 * @property int $id
 * @property int $doctor_id
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string|null $note
 * @property-read Doctor $doctor
 * @property-read Collection<int, DoctorScheduleBlock> $blocks
 */
class DoctorSchedule extends Model
{
    use Auditable, HasUserstamps;

    protected $fillable = ['doctor_id', 'effective_from', 'effective_to', 'note'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** @return HasMany<DoctorScheduleBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(DoctorScheduleBlock::class)->orderBy('weekday')->orderBy('start_time');
    }

    /**
     * Versions in force on the given date.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActiveOn(Builder $query, CarbonInterface $date): void
    {
        $day = $date->toDateString();
        $query->whereDate('effective_from', '<=', $day)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day));
    }

    public function hasStarted(): bool
    {
        return $this->effective_from->lte(today());
    }
}
