<?php

use App\Enums\RoleName;
use App\Http\Middleware\SignOutIdleSessions;
use App\Models\Setting;
use App\Models\User;
use App\Settings\Definitions;
use App\Settings\Settings;
use Livewire\Volt\Volt;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->artisan('app:sync-permissions');
    $this->admin = User::factory()->withRole(RoleName::SystemAdmin)->create();
});

describe('reading settings', function () {
    it('returns the default when nothing is stored', function () {
        expect(setting('security.idle_timeout_minutes'))->toBe(30)
            ->and(setting('prescriptions.printing_enabled'))->toBeTrue()
            ->and(setting('billing.discount_approval_percent'))->toBe('10.00')
            ->and(setting('clinic.address'))->toBeNull();
    });

    it('returns the stored value with the right type', function () {
        app(Settings::class)->setMany([
            'security.idle_timeout_minutes' => '45',
            'prescriptions.printing_enabled' => '0',
            'billing.discount_approval_percent' => '12.5',
        ]);

        expect(setting('security.idle_timeout_minutes'))->toBe(45)
            ->and(setting('prescriptions.printing_enabled'))->toBeFalse()
            ->and(setting('billing.discount_approval_percent'))->toBe('12.50');
    });

    it('throws on a misspelled key instead of silently returning null', function () {
        setting('billing.discount_aproval_percent');
    })->throws(InvalidArgumentException::class);

    it('only stores values that differ from the default', function () {
        app(Settings::class)->setMany(['security.idle_timeout_minutes' => 45]);
        expect(Setting::count())->toBe(1);

        app(Settings::class)->setMany(['security.idle_timeout_minutes' => 30]);
        expect(Setting::count())->toBe(0)
            ->and(setting('security.idle_timeout_minutes'))->toBe(30);
    });
});

describe('settings screen', function () {
    it('shows every setting to an administrator', function () {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.settings.index'))->assertOk();
        foreach (Definitions::all() as $definition) {
            $response->assertSee(__($definition->label));
        }
    });

    it('saves changes, records the user and audits old and new values', function () {
        $this->actingAs($this->admin);

        Volt::test('admin.settings.index')
            ->set('form.clinic__name', 'Smile Dental Care')
            ->set('form.billing__discount_approval_percent', '15')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('settings-saved');

        expect(setting('clinic.name'))->toBe('Smile Dental Care')
            ->and(setting('billing.discount_approval_percent'))->toBe('15.00')
            ->and(Setting::where('key', 'clinic.name')->value('created_by'))->toBe($this->admin->id);

        Volt::test('admin.settings.index')->set('form.billing__discount_approval_percent', '20')->call('save');

        $entry = Activity::where('log_name', 'settings')->where('event', 'updated')->latest('id')->firstOrFail();
        expect($entry->attribute_changes['old']['value'])->toBe('15.00')
            ->and($entry->attribute_changes['attributes']['value'])->toBe('20.00')
            ->and($entry->causer_id)->toBe($this->admin->id);
    });

    it('validates each setting by its own rules', function () {
        $this->actingAs($this->admin);

        Volt::test('admin.settings.index')
            ->set('form.security__idle_timeout_minutes', 1)
            ->set('form.billing__discount_approval_percent', 150)
            ->set('form.clinic__email', 'not-an-email')
            ->set('form.clinic__name', '')
            ->call('save')
            ->assertHasErrors([
                'form.security__idle_timeout_minutes',
                'form.billing__discount_approval_percent',
                'form.clinic__email',
                'form.clinic__name',
            ]);

        expect(Setting::count())->toBe(0);
    });

    it('lets the owner view but not change settings', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Owner)->create());

        $this->get(route('admin.settings.index'))->assertOk()->assertSee(__('You can view these settings but not change them.'));

        Volt::test('admin.settings.index')->set('form.clinic__name', 'Hacked')->call('save')->assertForbidden();
        expect(setting('clinic.name'))->toBe('Dental Clinic');
    });

    it('forbids roles without settings access', function (RoleName $role) {
        $this->actingAs(User::factory()->withRole($role)->create());

        $this->get(route('admin.settings.index'))->assertForbidden();
    })->with([RoleName::Receptionist, RoleName::Doctor, RoleName::Manager]);
});

describe('idle sign-out', function () {
    it('signs a user out after the configured idle time', function () {
        app(Settings::class)->setMany(['security.idle_timeout_minutes' => 10]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->travel(9)->minutes();
        $this->get(route('dashboard'))->assertOk();

        $this->travel(11)->minutes();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('keeps an active user signed in', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (range(1, 5) as $i) {
            $this->travel(20)->minutes();
            $this->get(route('dashboard'))->assertOk();
        }

        expect(session(SignOutIdleSessions::SESSION_KEY))->not->toBeNull();
    });
});
