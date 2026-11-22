<?php

namespace App\Actions\Users;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validation rules shared by CreateUser and UpdateUser.
 */
final class UserRules
{
    /** @return array<string, mixed> */
    public static function profile(?User $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class)->ignore($ignore?->id),
            ],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 \-]{6,20}$/'],
        ];
    }

    /** @return list<mixed> */
    public static function password(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'confirmed', Password::defaults()];
    }

    /** @return list<string> */
    public static function roles(): array
    {
        return ['required', 'array', 'min:1'];
    }

    /** @return list<mixed> */
    public static function role(): array
    {
        return ['string', Rule::enum(RoleName::class)];
    }
}
