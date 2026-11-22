<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Weekday helpers. Days are stored as Carbon's dayOfWeek (0 = Sunday … 6 = Saturday)
 * and shown in the Bangladeshi working-week order, Saturday first.
 */
final class Week
{
    /** @var list<int> Saturday → Friday */
    public const ORDER = [
        CarbonInterface::SATURDAY, CarbonInterface::SUNDAY, CarbonInterface::MONDAY, CarbonInterface::TUESDAY,
        CarbonInterface::WEDNESDAY, CarbonInterface::THURSDAY, CarbonInterface::FRIDAY,
    ];

    public static function name(int $weekday): string
    {
        return __([
            'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
        ][$weekday]);
    }

    public static function short(int $weekday): string
    {
        return __(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$weekday]);
    }
}
