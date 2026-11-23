<?php

namespace App\Settings;

enum SettingType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Text = 'text';
    case LongText = 'long_text';

    /** Convert a stored or submitted value to the PHP type the app uses. */
    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::Integer => (int) $value,
            self::Decimal => number_format((float) $value, 2, '.', ''),
            self::Text, self::LongText => trim((string) $value),
        };
    }
}
