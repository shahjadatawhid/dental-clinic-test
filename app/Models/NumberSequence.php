<?php

namespace App\Models;

use App\Enums\SequenceSeries;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Format of one numbered series. Take numbers only through App\Services\NumberSequenceService.
 *
 * @property SequenceSeries $series
 * @property string $prefix
 * @property string $format
 * @property int $padding
 * @property bool $reset_yearly
 */
class NumberSequence extends Model
{
    use Auditable, HasUserstamps;

    protected $fillable = ['series', 'prefix', 'format', 'padding', 'reset_yearly'];

    protected function casts(): array
    {
        return [
            'series' => SequenceSeries::class,
            'padding' => 'integer',
            'reset_yearly' => 'boolean',
        ];
    }

    /** @return HasMany<NumberSequenceCounter, $this> */
    public function counters(): HasMany
    {
        return $this->hasMany(NumberSequenceCounter::class);
    }

    public function periodFor(int $year): int
    {
        return $this->reset_yearly ? $year : 0;
    }

    /**
     * Tokens: {prefix}, {year} (2026), {yy} (26), {mm} (01–12), {number} (zero-padded counter).
     * Year and month come from the document's date (registration date, receipt date...).
     */
    public function formatNumber(int $value, CarbonInterface $date): string
    {
        return strtr($this->format, [
            '{prefix}' => $this->prefix,
            '{year}' => $date->format('Y'),
            '{yy}' => $date->format('y'),
            '{mm}' => $date->format('m'),
            '{number}' => str_pad((string) $value, $this->padding, '0', STR_PAD_LEFT),
        ]);
    }
}
