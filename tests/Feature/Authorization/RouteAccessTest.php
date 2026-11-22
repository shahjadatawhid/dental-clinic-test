<?php

use App\Authorization\Permissions;
use App\Enums\RoleName;
use App\Models\User;
use App\Navigation\Menu;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/*
 * Guards every route in the app, including ones added later:
 *  - every signed-in route needs a `permission:` middleware unless it is listed as personal below;
 *  - a user without that permission gets 403; a guest is sent to login;
 *  - menu links require exactly the permission their route checks.
 */

/** Routes every signed-in user may open (their own profile, the dashboard, sign-in housekeeping). */
const PERSONAL_ROUTES = [
    'dashboard', 'settings.profile', 'settings.password', 'settings.appearance',
    'verification.notice', 'verification.verify', 'password.confirm',
    'settings', // unnamed redirect to settings/profile
];

/** @return list<string> */
function routePermissions(Route $route): array
{
    return collect($route->gatherMiddleware())
        ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'))
        ->flatMap(fn ($m) => explode('|', substr($m, strlen('permission:'))))
        ->values()->all();
}

/** @return list<Route> */
function signedInRoutes(): array
{
    return collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $r) => in_array('auth', $r->gatherMiddleware(), true))
        ->filter(fn (Route $r) => in_array('GET', $r->methods(), true))
        ->values()->all();
}

/** Example values for route parameters. Add one here when a new parameter appears. */
function urlFor(Route $route): string
{
    $examples = [
        'user' => fn () => User::factory()->create()->id,
        'role' => fn () => RoleName::Receptionist->value,
        'id' => fn () => 1,
        'hash' => fn () => 'x',
    ];

    $params = [];
    foreach ($route->parameterNames() as $name) {
        expect($examples)->toHaveKey($name, message: "Add an example value for route parameter {{$name}} in RouteAccessTest");
        $params[$name] = $examples[$name]();
    }

    return $route->getName() !== null ? route($route->getName(), $params) : url($route->uri());
}

beforeEach(fn () => $this->artisan('app:sync-permissions'));

it('protects every signed-in route with a permission, except personal pages', function () {
    $unprotected = collect(signedInRoutes())
        ->reject(fn (Route $r) => in_array($r->getName() ?? $r->uri(), PERSONAL_ROUTES, true))
        ->reject(fn (Route $r) => routePermissions($r) !== [])
        ->map(fn (Route $r) => $r->getName() ?? $r->uri())
        ->values()->all();

    expect($unprotected)->toBe([], 'These routes need ->middleware(\'permission:...\'): '.implode(', ', $unprotected));
});

it('only uses permissions that exist', function () {
    $used = collect(signedInRoutes())->flatMap(fn (Route $r) => routePermissions($r))->unique();

    expect($used->diff(Permissions::all())->values()->all())->toBe([]);
});

it('returns 403 to a signed-in user without the permission, for every protected page', function () {
    $noAccess = User::factory()->create(); // no roles at all
    $this->actingAs($noAccess);

    $checked = 0;
    foreach (signedInRoutes() as $route) {
        if (routePermissions($route) === []) {
            continue;
        }
        $this->get(urlFor($route))->assertForbidden();
        $checked++;
    }

    expect($checked)->toBeGreaterThan(0);
});

it('sends guests to the login page from every signed-in page', function () {
    foreach (signedInRoutes() as $route) {
        if ($route->getName() === 'verification.verify') {
            continue; // signed URL: rejected before the auth check
        }
        $this->get(urlFor($route))->assertRedirect(route('login'));
    }
});

describe('menu', function () {
    it('asks for the same permissions as the page it links to', function () {
        foreach (Menu::sections() as $section) {
            foreach ($section['items'] as $item) {
                $route = RouteFacade::getRoutes()->getByName($item->route);

                expect($route)->not->toBeNull("Menu links to missing route {$item->route}")
                    ->and(routePermissions($route))->toEqualCanonicalizing($item->permissions, "Menu item {$item->label} and route {$item->route} disagree");
            }
        }
    });

    it('shows each role only the screens it can open', function (RoleName $role, array $visible, array $hidden) {
        $this->actingAs(User::factory()->withRole($role)->create());

        $response = $this->get(route('dashboard'))->assertOk();
        foreach ($visible as $label) {
            $response->assertSee(__($label));
        }
        foreach ($hidden as $label) {
            $response->assertDontSee(__($label));
        }
    })->with([
        'admin' => [RoleName::SystemAdmin, ['Users', 'Roles & permissions', 'Clinic settings', 'Audit history'], []],
        'accountant' => [RoleName::Accountant, ['Audit history'], ['Users', 'Clinic settings', 'Roles & permissions']],
        'receptionist' => [RoleName::Receptionist, ['Dashboard'], ['Administration', 'Audit history', 'Clinic settings']],
        'doctor' => [RoleName::Doctor, ['Dashboard'], ['Administration', 'Users']],
    ]);

    it('hides a section when none of its items are visible', function () {
        $receptionist = User::factory()->withRole(RoleName::Receptionist)->create();

        expect(collect(Menu::for($receptionist))->pluck('heading')->all())->not->toContain('Administration');
    });
});

describe('error pages', function () {
    it('shows a friendly 403 page with a way back', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Receptionist)->create());

        $this->get(route('admin.users.index'))
            ->assertForbidden()
            ->assertSee(__('You don’t have access to this page'))
            ->assertSee(__('Go to dashboard'));
    });

    it('shows a friendly 404 page', function () {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee(__('Page not found'))
            ->assertSee(__('Log in'));
    });
});
