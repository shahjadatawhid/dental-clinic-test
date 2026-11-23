<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity;

/**
 * Explicit audit entries for business events that a plain "updated" row
 * doesn't explain well: role changes, voids, reopenings, approvals...
 *
 *   Audit::record('voided', $receipt, 'Receipt voided', reason: $reason);
 *   Audit::record('roles_changed', $user, 'Roles changed', ['old' => [...], 'new' => [...]]);
 *
 * The signed-in user is stored as the causer automatically.
 */
final class Audit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $event,
        Model $subject,
        string $description,
        array $properties = [],
        ?string $reason = null,
        ?Model $causer = null,
    ): ?Activity {
        if ($reason !== null) {
            $properties['reason'] = $reason;
        }

        $logger = activity($subject->getTable())
            ->event($event)
            ->performedOn($subject)
            ->withProperties($properties);

        if ($causer !== null) {
            $logger->causedBy($causer);
        }

        return $logger->log($description);
    }
}
