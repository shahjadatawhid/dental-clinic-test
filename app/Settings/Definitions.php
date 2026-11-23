<?php

namespace App\Settings;

/**
 * Every clinic setting the app knows about. Add new settings here — the
 * settings screen, validation and defaults all come from this list.
 *
 * Read a value anywhere with:  setting('billing.discount_approval_percent')
 *
 * Note: number sequences (patient code, invoice, RV, PV prefixes) are configured
 * on the number_sequences table (M01-11), not here.
 */
final class Definitions
{
    /** @return array<string, SettingDefinition> key => definition */
    public static function all(): array
    {
        static $definitions = null;

        return $definitions ??= collect(self::list())->keyBy('key')->all();
    }

    public static function find(string $key): ?SettingDefinition
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<string, list<SettingDefinition>> group => definitions, in screen order */
    public static function grouped(): array
    {
        return collect(self::list())->groupBy('group')->map->values()->map->all()->all();
    }

    /** @return list<SettingDefinition> */
    private static function list(): array
    {
        return [
            // Clinic profile — printed on prescriptions, invoices and receipts.
            new SettingDefinition('clinic.name', 'Clinic profile', SettingType::Text, 'Dental Clinic',
                'Clinic name', rules: ['required']),
            new SettingDefinition('clinic.address', 'Clinic profile', SettingType::LongText, null,
                'Address'),
            new SettingDefinition('clinic.phone', 'Clinic profile', SettingType::Text, null,
                'Phone'),
            new SettingDefinition('clinic.email', 'Clinic profile', SettingType::Text, null,
                'Email', rules: ['email']),
            new SettingDefinition('clinic.registration_no', 'Clinic profile', SettingType::Text, null,
                'Registration / licence no.', 'Shown on printed documents if filled in.'),

            // Security
            new SettingDefinition('security.idle_timeout_minutes', 'Security', SettingType::Integer, 30,
                'Sign out after inactivity', 'Clinic computers are shared, so idle sessions are closed automatically.',
                ['required', 'min:5', 'max:480'], 'minutes'),

            // Appointments
            new SettingDefinition('appointments.default_slot_minutes', 'Appointments', SettingType::Integer, 15,
                'Default appointment length', 'Used when an appointment type has no length of its own.',
                ['required', 'min:5', 'max:240'], 'minutes'),

            // Prescriptions
            new SettingDefinition('prescriptions.printing_enabled', 'Prescriptions', SettingType::Boolean, true,
                'Allow printing prescriptions', 'When off, prescriptions are still saved but cannot be printed.'),
            new SettingDefinition('prescriptions.share_link_days', 'Prescriptions', SettingType::Integer, 7,
                'Share link valid for', 'How long a patient\'s prescription link works before it expires.',
                ['required', 'min:1', 'max:90'], 'days'),

            // Billing
            new SettingDefinition('billing.discount_approval_percent', 'Billing', SettingType::Decimal, '10.00',
                'Discount needing manager approval', 'Discounts above this percentage of an invoice need approval (Q3 — confirm with owner).',
                ['required', 'min:0', 'max:100'], '%'),
        ];
    }
}
