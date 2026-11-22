<?php

namespace App\Enums;

enum StaffType: string
{
    case Doctor = 'doctor';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Doctor => __('Doctor'),
            self::Staff => __('Staff'),
        };
    }
}
