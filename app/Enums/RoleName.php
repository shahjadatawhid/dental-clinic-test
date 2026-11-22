<?php

namespace App\Enums;

/**
 * Role templates from docs/04-roles-permissions.md.
 * An admin can adjust a role's permissions later; these are only starting points.
 */
enum RoleName: string
{
    case SystemAdmin = 'system_admin';
    case Owner = 'owner';
    case Manager = 'manager';
    case Receptionist = 'receptionist';
    case Doctor = 'doctor';
    case Accountant = 'accountant';
    case LabCoordinator = 'lab_coordinator';
    case InventoryStaff = 'inventory_staff';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => __('System administrator'),
            self::Owner => __('Owner / auditor'),
            self::Manager => __('Clinic manager'),
            self::Receptionist => __('Receptionist / cashier'),
            self::Doctor => __('Doctor'),
            self::Accountant => __('Accountant'),
            self::LabCoordinator => __('Lab coordinator'),
            self::InventoryStaff => __('Inventory staff'),
        };
    }
}
