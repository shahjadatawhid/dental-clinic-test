<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $number_sequence_id
 * @property int $period
 * @property int $last_value
 */
class NumberSequenceCounter extends Model
{
    protected $fillable = ['number_sequence_id', 'period', 'last_value'];

    protected function casts(): array
    {
        return ['period' => 'integer', 'last_value' => 'integer'];
    }

    /** @return BelongsTo<NumberSequence, $this> */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(NumberSequence::class, 'number_sequence_id');
    }
}
