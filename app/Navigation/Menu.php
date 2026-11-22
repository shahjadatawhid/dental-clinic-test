<?php

namespace App\Navigation;

use App\Models\User;

/**
 * The sidebar menu. Each module adds its screens here; an item is shown only to
 * users who have at least one of its permissions, and a section only when it has
 * at least one visible item.
 *
 * Hiding a link is a convenience, not security: every route must still have its
 * own `permission:` middleware (tests/Feature/Authorization/RouteAccessTest.php checks this).
 */
final class Menu
{
    /** @return list<array{heading: string|null, items: list<MenuItem>}> */
    public static function sections(): array
    {
        return [
            [
                'heading' => null,
                'items' => [
                    new MenuItem('Dashboard', 'home', 'dashboard', 'dashboard'),
                ],
            ],

            // Clinic modules (M03–M13) add their sections here, e.g.
            // ['heading' => 'Front desk', 'items' => [new MenuItem('Patients', 'user-group', 'patients.index', 'patients.*', ['patients.view'])]],

            [
                'heading' => 'Staff & schedules',
                'items' => [
                    new MenuItem('Staff', 'identification', 'staff.index', 'staff.*', ['staff.view']),
                    new MenuItem('Rooms & chairs', 'building-office', 'rooms.index', 'rooms.*', ['staff.view']),
                    new MenuItem('Doctor schedules', 'calendar-days', 'schedules.index', 'schedules.*', ['schedules.view']),
                    new MenuItem('Clinic holidays', 'sun', 'holidays.index', 'holidays.*', ['schedules.view']),
                ],
            ],

            [
                'heading' => 'Administration',
                'items' => [
                    new MenuItem('Users', 'users', 'admin.users.index', 'admin.users.*', ['users.view']),
                    new MenuItem('Roles & permissions', 'shield-check', 'admin.roles.index', 'admin.roles.*', ['roles.view']),
                    new MenuItem('Clinic settings', 'cog-6-tooth', 'admin.settings.index', 'admin.settings.*', ['settings.view']),
                    new MenuItem('Audit history', 'clipboard-document-list', 'admin.audit.index', 'admin.audit.*', ['audit.view']),
                ],
            ],
        ];
    }

    /** @return list<array{heading: string|null, items: list<MenuItem>}> sections with only the items this user may open */
    public static function for(User $user): array
    {
        $visible = [];

        foreach (self::sections() as $section) {
            $items = array_values(array_filter($section['items'], fn (MenuItem $item) => $item->visibleTo($user)));

            if ($items !== []) {
                $visible[] = ['heading' => $section['heading'], 'items' => $items];
            }
        }

        return $visible;
    }
}
