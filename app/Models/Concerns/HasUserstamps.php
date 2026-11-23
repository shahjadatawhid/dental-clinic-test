<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Fills `created_by` and `updated_by` with the signed-in user.
 * The table needs the columns — add them in the migration with `$table->userstamps();`.
 */
trait HasUserstamps
{
    protected static function bootHasUserstamps(): void
    {
        static::creating(function ($model) {
            $userId = Auth::id();
            $model->created_by ??= $userId;
            $model->updated_by ??= $userId;
        });

        static::updating(function ($model) {
            if (Auth::id() !== null) {
                $model->updated_by = Auth::id();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
