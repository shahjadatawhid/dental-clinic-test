<?php

use App\Actions\Users\SetUserActive;
use App\Actions\Users\UpdateUser;
use App\Enums\RoleName;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;
use Spatie\Activitylog\Models\Activity;

/** A throwaway model used only to test the shared traits. */
class AuditProbe extends Model
{
    use Auditable, HasUserstamps;

    protected $table = 'audit_probes';

    protected $guarded = [];

    protected $hidden = ['secret'];

    protected function auditExcept(): array
    {
        return ['noise'];
    }

    protected $casts = ['amount' => 'decimal:2'];
}

beforeEach(function () {
    $this->artisan('app:sync-permissions');

    Schema::create('audit_probes', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->decimal('amount', 12, 2)->default(0);
        $table->string('secret')->nullable();
        $table->string('noise')->nullable();
        $table->userstamps();
        $table->timestamps();
    });
});

afterEach(fn () => Schema::dropIfExists('audit_probes'));

describe('Auditable trait', function () {
    it('logs create, update with old/new values, and delete with the acting user', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $probe = AuditProbe::create(['name' => 'Scaling', 'amount' => 1000]);
        $probe->update(['amount' => 1500]);
        $probe->delete();

        $log = Activity::where('log_name', 'audit_probes')->orderBy('id')->get();

        expect($log->pluck('event')->all())->toBe(['created', 'updated', 'deleted'])
            ->and($log->every(fn ($a) => $a->causer_id === $user->id))->toBeTrue()
            ->and($log[1]->attribute_changes['old'])->toBe(['amount' => '1000.00'])
            ->and($log[1]->attribute_changes['attributes'])->toBe(['amount' => '1500.00']);
    });

    it('never logs hidden or excluded columns, and skips empty changes', function () {
        $this->actingAs(User::factory()->create());
        $probe = AuditProbe::create(['name' => 'A', 'secret' => 'pin-1234', 'noise' => 'x']);

        $probe->update(['noise' => 'y']); // only an excluded column changed

        $entries = Activity::where('log_name', 'audit_probes')->get();
        expect($entries)->toHaveCount(1)
            ->and($entries[0]->attribute_changes['attributes'])->not->toHaveKeys(['secret', 'noise', 'created_at', 'updated_at']);
    });
});

describe('HasUserstamps trait', function () {
    it('fills created_by and updated_by from the signed-in user', function () {
        $creator = User::factory()->create();
        $editor = User::factory()->create();

        $this->actingAs($creator);
        $probe = AuditProbe::create(['name' => 'A']);
        expect($probe->created_by)->toBe($creator->id)->and($probe->updated_by)->toBe($creator->id);

        $this->actingAs($editor);
        $probe->update(['name' => 'B']);
        expect($probe->fresh()->created_by)->toBe($creator->id)
            ->and($probe->fresh()->updated_by)->toBe($editor->id)
            ->and($probe->fresh()->creator->is($creator))->toBeTrue();
    });
});

describe('Audit helper', function () {
    it('records a business event with a reason', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $probe = AuditProbe::create(['name' => 'Receipt']);

        Audit::record('voided', $probe, 'Receipt voided', ['amount' => 500], reason: 'Entered twice');

        $entry = Activity::where('event', 'voided')->firstOrFail();
        expect($entry->properties['reason'])->toBe('Entered twice')
            ->and($entry->properties['amount'])->toBe(500)
            ->and($entry->causer_id)->toBe($user->id)
            ->and($entry->subject_id)->toBe($probe->id);
    });
});

describe('user administration is audited', function () {
    it('logs role changes with old and new roles', function () {
        $admin = User::factory()->withRole(RoleName::SystemAdmin)->create();
        $user = User::factory()->withRole(RoleName::Receptionist)->create();
        $this->actingAs($admin);

        app(UpdateUser::class)->handle($admin, $user, [
            'name' => $user->name, 'email' => $user->email,
            'roles' => [RoleName::Receptionist->value, RoleName::Manager->value],
        ]);

        $entry = Activity::where('event', 'roles_changed')->where('subject_id', $user->id)->latest('id')->firstOrFail();
        expect($entry->properties['old'])->toBe(['receptionist'])
            ->and($entry->properties['new'])->toBe(['manager', 'receptionist'])
            ->and($entry->causer_id)->toBe($admin->id);
    });

    it('logs a password reset without storing the password', function () {
        $admin = User::factory()->withRole(RoleName::SystemAdmin)->create();
        $user = User::factory()->withRole(RoleName::Doctor)->create();
        $this->actingAs($admin);

        app(UpdateUser::class)->handle($admin, $user, [
            'name' => $user->name, 'email' => $user->email, 'roles' => [RoleName::Doctor->value],
            'password' => 'new-secret-123', 'password_confirmation' => 'new-secret-123',
        ]);

        expect(Activity::where('event', 'password_reset')->where('subject_id', $user->id)->exists())->toBeTrue()
            ->and(Activity::all()->toJson())->not->toContain('new-secret-123')
            ->and(Activity::all()->toJson())->not->toContain($user->fresh()->password);
    });

    it('logs deactivation', function () {
        $admin = User::factory()->withRole(RoleName::SystemAdmin)->create();
        $user = User::factory()->create();
        $this->actingAs($admin);

        app(SetUserActive::class)->handle($admin, $user, false);

        expect(Activity::where('event', 'deactivated')->where('subject_id', $user->id)->value('causer_id'))->toBe($admin->id);
    });
});

describe('sign-in activity', function () {
    it('logs successful login and logout', function () {
        $user = User::factory()->create();

        Volt::test('auth.login')->set('email', $user->email)->set('password', 'password')->call('login');
        $this->post(route('logout'));

        expect(Activity::inLog('auth')->where('subject_id', $user->id)->pluck('event')->all())
            ->toBe(['login', 'logout']);
    });

    it('logs failed logins without the password', function () {
        $user = User::factory()->create();

        Volt::test('auth.login')->set('email', $user->email)->set('password', 'wrong-guess')->call('login');

        $entry = Activity::inLog('auth')->where('event', 'login_failed')->firstOrFail();
        expect($entry->properties['email'])->toBe($user->email)
            ->and($entry->subject_id)->toBe($user->id)
            ->and($entry->toJson())->not->toContain('wrong-guess');
    });

    it('logs blocked logins of deactivated users', function () {
        $user = User::factory()->inactive()->create();

        Volt::test('auth.login')->set('email', $user->email)->set('password', 'password')->call('login');

        expect(Activity::where('event', 'login_blocked')->where('subject_id', $user->id)->exists())->toBeTrue();
    });
});
