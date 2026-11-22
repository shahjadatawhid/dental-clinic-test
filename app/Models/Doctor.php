<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Doctor-specific details. Name, phone and active status come from the staff record.
 *
 * @property int $id
 * @property int $staff_id
 * @property string|null $registration_no
 * @property string|null $specialty
 * @property string|null $qualifications
 * @property string|null $signature_path
 * @property int|null $default_slot_minutes
 * @property string $calendar_color
 * @property bool $is_bookable
 * @property-read Staff $staff
 * @property-read string $name
 */
class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
    use Auditable, HasFactory, HasUserstamps;

    protected $fillable = [
        'staff_id', 'registration_no', 'specialty', 'qualifications', 'signature_path',
        'default_slot_minutes', 'calendar_color', 'is_bookable',
    ];

    protected $attributes = [
        'calendar_color' => '#0ea5e9',
        'is_bookable' => true,
    ];

    protected function casts(): array
    {
        return [
            'default_slot_minutes' => 'integer',
            'is_bookable' => 'boolean',
        ];
    }

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function getNameAttribute(): string
    {
        return $this->staff->full_name;
    }

    /** Appointment length to use when the appointment type doesn't set one. */
    public function slotMinutes(): int
    {
        return $this->default_slot_minutes ?? (int) setting('appointments.default_slot_minutes');
    }

    /**
     * Doctors who can be booked: bookable and their staff record active.
     *
     * @param  Builder<self>  $query
     */
    public function scopeBookable(Builder $query): void
    {
        $query->where('is_bookable', true)
            ->whereHas('staff', fn (Builder $q) => $q->where('is_active', true));
    }
}
