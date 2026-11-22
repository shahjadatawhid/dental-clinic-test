<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\ChairFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $room_id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property-read Room $room
 * @property-read string $label
 */
class Chair extends Model
{
    /** @use HasFactory<ChairFactory> */
    use Auditable, HasFactory, HasUserstamps;

    protected $fillable = ['room_id', 'name', 'sort_order', 'is_active'];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** "Room 1 · Chair A" — for calendars and dropdowns. */
    public function getLabelAttribute(): string
    {
        return $this->room->name.' · '.$this->name;
    }

    /**
     * A chair can be used only if it and its room are active.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('room', fn (Builder $q) => $q->where('is_active', true));
    }
}
