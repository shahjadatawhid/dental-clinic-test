<?php

namespace App\Models;

use App\Enums\StaffType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A person who works at the clinic. Doctors also have a Doctor record.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $full_name
 * @property StaffType $type
 * @property string|null $designation
 * @property string|null $gender
 * @property string|null $phone
 * @property string|null $email
 * @property Carbon|null $joined_on
 * @property Carbon|null $left_on
 * @property bool $is_active
 * @property string|null $notes
 * @property-read Doctor|null $doctor
 * @property-read User|null $user
 */
class Staff extends Model
{
    /** @use HasFactory<StaffFactory> */
    use Auditable, HasFactory, HasUserstamps;

    protected $table = 'staff';

    protected $fillable = [
        'user_id', 'full_name', 'type', 'designation', 'gender', 'phone', 'email',
        'joined_on', 'left_on', 'is_active', 'notes',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => StaffType::class,
            'joined_on' => 'date',
            'left_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<Doctor, $this> */
    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function isDoctor(): bool
    {
        return $this->type === StaffType::Doctor;
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
