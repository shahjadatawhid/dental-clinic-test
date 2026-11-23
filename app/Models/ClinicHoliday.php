<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $date
 * @property string $name
 */
class ClinicHoliday extends Model
{
    use Auditable, HasUserstamps;

    protected $fillable = ['date', 'name'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public static function forDate(CarbonInterface $date): ?self
    {
        return static::whereDate('date', $date->toDateString())->first();
    }
}
