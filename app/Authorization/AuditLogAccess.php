<?php

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Audit entries copy the data that changed, so a clinical change in the audit log is
 * as sensitive as the clinical record itself. Someone with `audit.view` only sees a
 * log if they may also see the underlying records.
 *
 * When a module adds an Auditable model with sensitive data, add its table here.
 */
final class AuditLogAccess
{
    /** @var array<string, string> log name (table) => permission needed to see it */
    public const RESTRICTED = [
        // M03 clinical patient data
        'patient_alerts' => 'patients.view_clinical',
        'patient_medical_history' => 'patients.view_clinical',
        // M05 clinical
        'visits' => 'visits.view',
        'treatments' => 'visits.view',
        'treatment_plans' => 'visits.view',
        'treatment_plan_items' => 'visits.view',
        'tooth_records' => 'visits.view',
        // M06 prescriptions
        'prescriptions' => 'prescriptions.view',
        'prescription_items' => 'prescriptions.view',
        // M10 settlements
        'settlements' => 'settlements.view',
        'settlement_plans' => 'settlements.view',
    ];

    /** @return list<string> log names this user may not see */
    public static function hiddenFor(User $user): array
    {
        return array_keys(array_filter(
            self::RESTRICTED,
            fn (string $permission) => ! $user->can($permission),
        ));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scope(Builder $query, User $user): Builder
    {
        $hidden = self::hiddenFor($user);

        return $hidden === []
            ? $query
            : $query->where(fn ($q) => $q->whereNull('log_name')->orWhereNotIn('log_name', $hidden));
    }
}
