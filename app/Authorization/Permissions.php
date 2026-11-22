<?php

namespace App\Authorization;

use App\Enums\RoleName;

/**
 * Single source of truth for permission names and the starting role templates.
 *
 * Naming: "<resource>.<action>". Scoping such as "only my own patients" or
 * "only my own settlement" is enforced in Policies, not here.
 *
 * To add a permission: add it to ALL, give it to the right roles in
 * roleTemplates(), then run: php artisan db:seed --class=RolesAndPermissionsSeeder
 */
final class Permissions
{
    /** @var array<string, string> permission => description */
    public const ALL = [
        // M01 Administration
        'users.view' => 'View user accounts',
        'users.manage' => 'Create, update, deactivate user accounts',
        'roles.view' => 'View roles and permissions',
        'roles.manage' => 'Change roles and permissions (audited)',
        'settings.view' => 'View clinic settings',
        'settings.manage' => 'Change clinic settings',
        'audit.view' => 'View audit history',

        // M02 Staff & schedules
        'staff.view' => 'View staff and doctor profiles',
        'staff.manage' => 'Create and update staff, rooms, chairs',
        'schedules.view' => 'View doctor schedules',
        'schedules.manage' => 'Edit working hours, leave, holidays',

        // M03 Patients
        'patients.view' => 'View patient demographics',
        'patients.view_minimal' => 'View only name/code needed to identify a patient',
        'patients.create' => 'Register patients',
        'patients.update' => 'Edit patient demographics',
        'patients.merge' => 'Merge duplicate patients',
        'patients.export' => 'Export patient lists',
        'patients.view_alerts' => 'View medical alerts and allergies',
        'patients.view_clinical' => 'View medical history and clinical details',
        'patients.update_clinical' => 'Record medical history and alerts',

        // M04 Appointments
        'appointments.view' => 'View all appointments',
        'appointments.view_own' => 'View own appointments (doctor)',
        'appointments.create' => 'Book appointments',
        'appointments.update' => 'Reschedule, check in, change status',
        'appointments.cancel' => 'Cancel or mark no-show',

        // M05 Clinical
        'visits.view' => 'View visit notes and treatments',
        'visits.create' => 'Start visits and record treatments',
        'visits.update' => 'Edit visit notes',
        'procedures.view' => 'View procedure catalog',
        'procedures.manage' => 'Edit procedure catalog and prices',

        // M06 Prescriptions
        'medicines.view' => 'Search medicine catalog',
        'medicines.manage' => 'Edit medicine catalog',
        'prescriptions.view' => 'View prescriptions',
        'prescriptions.create' => 'Write prescriptions',
        'prescriptions.void' => 'Cancel or replace prescriptions',
        'prescriptions.print' => 'Print or share prescriptions',

        // M07 Billing
        'invoices.view' => 'View invoices',
        'invoices.view_own' => 'View invoices for own patients (doctor)',
        'invoices.create' => 'Create and issue invoices',
        'invoices.update' => 'Edit draft invoices',
        'invoices.approve_discount' => 'Approve discounts above the limit',
        'invoices.void' => 'Void invoices',
        'invoices.export' => 'Export invoices',
        'payments.view' => 'View receipts (RV)',
        'payments.create' => 'Record payments (RV)',
        'payments.void' => 'Void receipts',
        'payments.export' => 'Export receipts',
        'refunds.request' => 'Request a refund',
        'refunds.approve' => 'Approve refunds and write-offs',

        // M08 Expenses
        'expenses.view' => 'View expenses and payment vouchers (PV)',
        'expenses.create' => 'Create payment vouchers',
        'expenses.update' => 'Edit draft payment vouchers',
        'expenses.approve' => 'Approve payment vouchers',
        'expenses.void' => 'Void payment vouchers',
        'expenses.export' => 'Export expenses',

        // M09 Closing & periods
        'closing.view' => 'View daily closings',
        'closing.create' => 'Close the day',
        'closing.reopen' => 'Reopen a closed day',
        'periods.view' => 'View accounting periods',
        'periods.manage' => 'Close and reopen accounting periods',

        // M10 Settlements
        'settlements.view' => 'View all doctor settlements',
        'settlements.view_own' => 'View own settlement (doctor)',
        'settlements.create' => 'Generate settlement statements and plans',
        'settlements.approve' => 'Approve settlement statements',
        'settlements.export' => 'Export settlements',

        // M11 Lab
        'lab.view' => 'View lab orders',
        'lab.create' => 'Create lab orders',
        'lab.update' => 'Update lab order status',
        'lab.view_cost' => 'View lab costs',

        // M12 Inventory
        'inventory.view' => 'View stock and suppliers',
        'inventory.manage' => 'Edit items, suppliers, purchases',
        'inventory.adjust' => 'Record stock movements and adjustments',

        // M13 Reports
        'reports.daily' => 'View daily collection and appointment reports',
        'reports.finance' => 'View finance reports',
        'reports.clinical' => 'View clinical activity reports',
        'reports.stock' => 'View stock reports',
        'reports.export' => 'Export reports (logged)',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::ALL);
    }

    /**
     * Starting permissions per role (docs/04-roles-permissions.md).
     *
     * @return array<string, list<string>> role value => permissions
     */
    public static function roleTemplates(): array
    {
        return [
            RoleName::SystemAdmin->value => [
                'users.view', 'users.manage', 'roles.view', 'roles.manage',
                'settings.view', 'settings.manage', 'audit.view',
                'staff.view', 'schedules.view',
            ],

            RoleName::Owner->value => [
                'users.view', 'roles.view', 'settings.view', 'audit.view',
                'staff.view', 'schedules.view',
                'patients.view',
                'appointments.view',
                'procedures.view', 'medicines.view',
                'invoices.view', 'payments.view',
                'expenses.view', 'closing.view', 'periods.view',
                'settlements.view', 'settlements.approve',
                'lab.view', 'lab.view_cost', 'inventory.view',
                'reports.daily', 'reports.finance', 'reports.clinical', 'reports.stock', 'reports.export',
            ],

            RoleName::Manager->value => [
                'audit.view',
                'staff.view', 'staff.manage', 'schedules.view', 'schedules.manage',
                'patients.view', 'patients.create', 'patients.update', 'patients.merge', 'patients.export',
                'patients.view_alerts', 'patients.view_clinical',
                'appointments.view', 'appointments.create', 'appointments.update', 'appointments.cancel',
                'visits.view', 'procedures.view', 'procedures.manage',
                'medicines.view', 'medicines.manage', 'prescriptions.view',
                'invoices.view', 'invoices.create', 'invoices.update', 'invoices.approve_discount', 'invoices.void',
                'payments.view', 'payments.create', 'payments.void',
                'refunds.request', 'refunds.approve',
                'expenses.view', 'expenses.create', 'expenses.approve',
                'closing.view', 'closing.reopen',
                'settlements.view',
                'lab.view', 'lab.create', 'lab.update',
                'inventory.view',
                'reports.daily', 'reports.finance', 'reports.clinical', 'reports.stock', 'reports.export',
            ],

            RoleName::Receptionist->value => [
                'staff.view', 'schedules.view',
                'patients.view', 'patients.create', 'patients.update', 'patients.view_alerts',
                'appointments.view', 'appointments.create', 'appointments.update', 'appointments.cancel',
                'procedures.view',
                'prescriptions.view', 'prescriptions.print',
                'invoices.view', 'invoices.create', 'invoices.update',
                'payments.view', 'payments.create',
                'refunds.request',
                'closing.view', 'closing.create',
                'lab.view', 'lab.create',
                'reports.daily',
            ],

            RoleName::Doctor->value => [
                'schedules.view',
                'patients.view', 'patients.view_alerts', 'patients.view_clinical', 'patients.update_clinical',
                'appointments.view_own', 'appointments.update',
                'visits.view', 'visits.create', 'visits.update',
                'procedures.view', 'medicines.view',
                'prescriptions.view', 'prescriptions.create', 'prescriptions.void', 'prescriptions.print',
                'invoices.view_own',
                'settlements.view_own',
                'lab.view', 'lab.create', 'lab.update',
            ],

            RoleName::Accountant->value => [
                'audit.view',
                'patients.view',
                'procedures.view',
                'invoices.view', 'invoices.export',
                'payments.view', 'payments.export',
                'refunds.approve',
                'expenses.view', 'expenses.create', 'expenses.update', 'expenses.void', 'expenses.export',
                'closing.view', 'periods.view', 'periods.manage',
                'settlements.view', 'settlements.create', 'settlements.export',
                'lab.view', 'lab.view_cost',
                'inventory.view',
                'reports.daily', 'reports.finance', 'reports.export',
            ],

            RoleName::LabCoordinator->value => [
                'patients.view_minimal',
                'lab.view', 'lab.create', 'lab.update',
            ],

            RoleName::InventoryStaff->value => [
                'inventory.view', 'inventory.manage', 'inventory.adjust',
                'expenses.create',
                'reports.stock',
            ],
        ];
    }
}
