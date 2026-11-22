<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A patient. Register through App\Actions\Patients\RegisterPatient so the patient code
 * is assigned correctly; never delete (deactivate or merge instead).
 *
 * @property int $id
 * @property string $patient_code
 * @property string|null $legacy_code
 * @property Carbon $registered_on
 * @property string $full_name
 * @property string|null $gender
 * @property Carbon|null $date_of_birth
 * @property int|null $age_years
 * @property Carbon|null $age_recorded_on
 * @property string|null $phone
 * @property string|null $alt_phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $occupation
 * @property string $preferred_language
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $guardian_name
 * @property string|null $guardian_relation
 * @property string|null $referred_by
 * @property string|null $notes
 * @property bool $is_active
 * @property int|null $merged_into_id
 * @property-read Patient|null $mergedInto
 */
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use Auditable, HasFactory, HasUserstamps;

    /** patient_code, registered_on and merged_into_id are set by actions only. */
    protected $fillable = [
        'legacy_code', 'full_name', 'gender', 'date_of_birth', 'age_years', 'age_recorded_on',
        'phone', 'alt_phone', 'email', 'address', 'occupation', 'preferred_language',
        'emergency_contact_name', 'emergency_contact_phone', 'guardian_name', 'guardian_relation',
        'referred_by', 'notes', 'is_active',
    ];

    /** Who may bring a child or sign for them. */
    public const GUARDIAN_RELATIONS = ['mother', 'father', 'grandparent', 'sibling', 'spouse', 'other'];

    /** Below this age the form asks for a parent or guardian. */
    public const ADULT_AGE = 18;

    protected $attributes = [
        'is_active' => true,
        'preferred_language' => 'bn',
    ];

    protected function casts(): array
    {
        return [
            'registered_on' => 'date',
            'date_of_birth' => 'date',
            'age_recorded_on' => 'date',
            'age_years' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'merged_into_id');
    }

    /**
     * Age today: from date of birth, or from the age given at registration plus the
     * years since. Null if neither is known.
     */
    public function age(?Carbon $on = null): ?int
    {
        $on ??= today();

        if ($this->date_of_birth !== null) {
            return (int) $this->date_of_birth->diffInYears($on);
        }

        if ($this->age_years !== null) {
            $since = $this->age_recorded_on ?? $this->registered_on;

            return $this->age_years + (int) $since->diffInYears($on);
        }

        return null;
    }

    /** "Nusrat Jahan · F · 34y" for headers and pickers. */
    public function summary(): string
    {
        $gender = match ($this->gender) {
            'female' => 'F',
            'male' => 'M',
            'other' => 'O',
            default => null,
        };
        $age = $this->age();

        return collect([$this->full_name, $gender, $age !== null ? "{$age}y" : null])->filter()->implode(' · ');
    }

    /**
     * Records that can be used: active and not merged away.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->whereNull('merged_into_id');
    }
}
