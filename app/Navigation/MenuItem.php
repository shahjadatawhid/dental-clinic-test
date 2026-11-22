<?php

namespace App\Navigation;

use App\Models\User;

final readonly class MenuItem
{
    /**
     * @param  string  $icon  Heroicon name (https://heroicons.com), as used by <flux:navlist.item icon="...">
     * @param  string  $active  route name pattern that highlights this item, e.g. "admin.users.*"
     * @param  list<string>  $permissions  any one of these shows the item; empty = every signed-in user
     */
    public function __construct(
        public string $label,
        public string $icon,
        public string $route,
        public string $active,
        public array $permissions = [],
    ) {}

    public function visibleTo(User $user): bool
    {
        return $this->permissions === [] || $user->canAny($this->permissions);
    }

    public function url(): string
    {
        return route($this->route);
    }

    public function isCurrent(): bool
    {
        return request()->routeIs($this->active);
    }
}
