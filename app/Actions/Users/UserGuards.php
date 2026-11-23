<?php

namespace App\Actions\Users;

use App\Enums\RoleName;
use App\Models\User;

final class UserGuards
{
    public static function activeAdminCount(): int
    {
        return User::active()->role(RoleName::SystemAdmin->value)->count();
    }
}
