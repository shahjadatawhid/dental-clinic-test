<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records every create / update / delete of the model in the audit log
 * (`activity_log` table) with old and new values and the user who did it.
 *
 * Usage: `use Auditable;` on any model with clinical or financial meaning.
 * To keep extra columns out of the log, override in the model:
 *     protected function auditExcept(): array { return ['some_column']; }
 * Hidden attributes ($hidden, e.g. password) are never logged.
 */
trait Auditable
{
    use LogsActivity {
        buildChanges as protected baseBuildChanges;
    }

    /**
     * On update, keep only columns that this save actually changed. Without this, a column
     * filled by a database default (missing from the in-memory model) shows up as "null → value".
     *
     * @return array<string, mixed>
     */
    protected function buildChanges(string $processingEvent): array
    {
        $changes = $this->baseBuildChanges($processingEvent);

        if ($processingEvent === 'updated') {
            $changed = array_flip(array_keys($this->getChanges()));
            foreach (['attributes', 'old'] as $key) {
                if (isset($changes[$key])) {
                    $changes[$key] = array_intersect_key($changes[$key], $changed);
                }
            }
        }

        return $changes;
    }

    /**
     * Extra columns to keep out of the audit log. Override in the model when needed.
     *
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return [];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept([
                'created_at', 'updated_at',
                ...$this->getHidden(),
                ...$this->auditExcept(),
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName($this->getTable());
    }
}
