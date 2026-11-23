<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $notes
 * @property int $sort_order
 * @property bool $is_active
 */
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use Auditable, HasFactory, HasUserstamps;

    protected $fillable = ['name', 'notes', 'sort_order', 'is_active'];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return HasMany<Chair, $this> */
    public function chairs(): HasMany
    {
        return $this->hasMany(Chair::class)->orderBy('sort_order')->orderBy('name');
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
