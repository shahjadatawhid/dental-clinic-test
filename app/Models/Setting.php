<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Model;

/**
 * Stored value of one clinic setting. Read values through setting() / App\Settings\Settings,
 * not this model directly, so defaults and types are applied.
 *
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    use Auditable, HasUserstamps;

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
