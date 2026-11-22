<?php

namespace App\Enums;

enum TimeOffType: string
{
    case Leave = 'leave';      // whole days away
    case Blocked = 'blocked';  // part of a day: meeting, surgery elsewhere, personal

    public function label(): string
    {
        return match ($this) {
            self::Leave => __('Leave'),
            self::Blocked => __('Blocked time'),
        };
    }
}
