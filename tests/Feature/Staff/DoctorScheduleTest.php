<?php

use App\Actions\Schedules\RemoveUpcomingSchedule;
use App\Actions\Schedules\SaveDoctorSchedule;
use App\Enums\RoleName;
use App\Models\Chair;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->artisan('app:sync-permissions');
    $this->doctor = Doctor::factory()->create();
    $this->save = app(SaveDoctorSchedule::class);
    Carbon::setTestNow('2026-11-22 10:00'); // a Sunday
});

afterEach(fn () => Carbon::setTestNow());

/** Saturday 10–13 + 17–21, Sunday 17–21 */
function routine(): array
{
    return [
        ['weekday' => CarbonInterface::SATURDAY, 'start_time' => '10:00', 'end_time' => '13:00'],
        ['weekday' => CarbonInterface::SATURDAY, 'start_time' => '17:00', 'end_time' => '21:00'],
        ['weekday' => CarbonInterface::SUNDAY, 'start_time' => '17:00', 'end_time' => '21:00'],
    ];
}

describe('looking up hours', function () {
    it('returns the blocks for the weekday of a date, earliest first', function () {
        $this->save->handle($this->doctor, '2026-11-01', routine());

        $saturday = Carbon::parse('2026-11-28');
        expect($this->doctor->hoursOn($saturday)->map->timeRange()->all())->toBe(['10:00–13:00', '17:00–21:00'])
            ->and($this->doctor->hoursOn(Carbon::parse('2026-11-27'))->isEmpty())->toBeTrue(); // Friday off
    });

    it('has no hours before the first routine starts', function () {
        $this->save->handle($this->doctor, '2026-12-01', routine());

        expect($this->doctor->hoursOn(Carbon::parse('2026-11-28')))->toHaveCount(0)
            ->and($this->doctor->hoursOn(Carbon::parse('2026-12-05')))->toHaveCount(2);
    });
});

describe('versions', function () {
    it('starts a new version and closes the old one the day before, keeping history', function () {
        $this->save->handle($this->doctor, '2026-11-01', routine());
        $this->save->handle($this->doctor, '2026-12-15', [
            ['weekday' => CarbonInterface::SATURDAY, 'start_time' => '09:00', 'end_time' => '12:00'],
        ]);

        $old = DoctorSchedule::where('effective_from', '2026-11-01')->first();
        expect($old->effective_to->toDateString())->toBe('2026-12-14')
            ->and($this->doctor->hoursOn(Carbon::parse('2026-12-12'))->map->timeRange()->all())->toBe(['10:00–13:00', '17:00–21:00'])
            ->and($this->doctor->hoursOn(Carbon::parse('2026-12-19'))->map->timeRange()->all())->toBe(['09:00–12:00'])
            ->and($this->doctor->hoursOn(Carbon::parse('2026-12-20')))->toHaveCount(0); // Sunday no longer worked
    });

    it('replaces the blocks when saving the same start date again', function () {
        $this->save->handle($this->doctor, '2026-11-01', routine());
        $this->save->handle($this->doctor, '2026-11-01', [
            ['weekday' => CarbonInterface::MONDAY, 'start_time' => '18:00', 'end_time' => '20:00'],
        ]);

        expect(DoctorSchedule::count())->toBe(1)
            ->and(DoctorSchedule::first()->blocks)->toHaveCount(1);
    });

    it('refuses a start date before the newest version', function () {
        $this->save->handle($this->doctor, '2026-12-01', routine());

        expect(fn () => $this->save->handle($this->doctor, '2026-11-15', routine()))
            ->toThrow(ValidationException::class);
        expect(DoctorSchedule::count())->toBe(1);
    });

    it('keeps each doctor\'s versions separate', function () {
        $other = Doctor::factory()->create();
        $this->save->handle($this->doctor, '2026-11-01', routine());
        $this->save->handle($other, '2026-12-01', routine());

        expect($this->doctor->scheduleOn(Carbon::parse('2026-12-05'))->effective_to)->toBeNull();
    });

    it('removes an upcoming version and reopens the previous one', function () {
        $this->save->handle($this->doctor, '2026-11-01', routine());
        $upcoming = $this->save->handle($this->doctor, '2026-12-15', routine());

        app(RemoveUpcomingSchedule::class)->handle($upcoming);

        expect(DoctorSchedule::count())->toBe(1)
            ->and(DoctorSchedule::first()->effective_to)->toBeNull();
    });

    it('never removes a version that has started', function () {
        $current = $this->save->handle($this->doctor, '2026-11-01', routine());

        expect(fn () => app(RemoveUpcomingSchedule::class)->handle($current))->toThrow(ValidationException::class);
    });
});

describe('validation', function () {
    it('rejects end times before start times and overlapping blocks on a day', function () {
        expect(fn () => $this->save->handle($this->doctor, '2026-11-01', [
            ['weekday' => 6, 'start_time' => '13:00', 'end_time' => '10:00'],
        ]))->toThrow(ValidationException::class);

        expect(fn () => $this->save->handle($this->doctor, '2026-11-01', [
            ['weekday' => 6, 'start_time' => '10:00', 'end_time' => '13:00'],
            ['weekday' => 6, 'start_time' => '12:00', 'end_time' => '15:00'],
        ]))->toThrow(ValidationException::class);
    });

    it('allows back-to-back blocks and the same times on different days', function () {
        $this->save->handle($this->doctor, '2026-11-01', [
            ['weekday' => 6, 'start_time' => '10:00', 'end_time' => '13:00'],
            ['weekday' => 6, 'start_time' => '13:00', 'end_time' => '15:00'],
            ['weekday' => 0, 'start_time' => '10:00', 'end_time' => '13:00'],
        ]);

        expect(DoctorSchedule::first()->blocks)->toHaveCount(3);
    });

    it('only accepts chairs that are usable', function () {
        $chair = Chair::factory()->create();
        $inactive = Chair::factory()->inactive()->create();

        $this->save->handle($this->doctor, '2026-11-01', [
            ['weekday' => 6, 'start_time' => '10:00', 'end_time' => '13:00', 'chair_id' => $chair->id],
        ]);
        expect($this->doctor->hoursOn(Carbon::parse('2026-11-28'))->first()->chair->is($chair))->toBeTrue();

        expect(fn () => $this->save->handle($this->doctor, '2026-11-01', [
            ['weekday' => 6, 'start_time' => '10:00', 'end_time' => '13:00', 'chair_id' => $inactive->id],
        ]))->toThrow(ValidationException::class);
    });
});

describe('screens', function () {
    it('shows each doctor\'s weekly hours on the overview', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Receptionist)->create());
        $this->save->handle($this->doctor, '2026-11-01', routine());

        $this->get(route('schedules.index'))->assertOk()
            ->assertSee($this->doctor->name)
            ->assertSee('10:00–13:00')
            ->assertSee('17:00–21:00');
    });

    it('lets a manager edit hours, with errors shown on the right row', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Manager)->create());

        Volt::test('schedules.edit', ['doctor' => $this->doctor])
            ->assertSet('effectiveFrom', '2026-11-22')
            ->call('addBlock', CarbonInterface::SATURDAY)
            ->assertSet('days.6.0.start_time', '17:00')
            ->call('copyToWorkingDays', CarbonInterface::SATURDAY)
            ->assertSet('days.4.0.end_time', '21:00')   // Thursday copied
            ->assertSet('days.5', [])                   // Friday left off
            ->set('days.1.0.end_time', '16:00')         // Monday ends before it starts
            ->call('save')
            ->assertHasErrors(['days.1.0.end_time'])
            ->set('days.1.0.end_time', '21:00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('schedules.index'));

        expect($this->doctor->hoursOn(Carbon::parse('2026-11-26')))->toHaveCount(1); // Thursday
    });

    it('opens with the latest routine filled in', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Manager)->create());
        $this->save->handle($this->doctor, '2026-11-01', routine(), 'Winter hours');

        Volt::test('schedules.edit', ['doctor' => $this->doctor])
            ->assertSet('days.6.1.start_time', '17:00')
            ->assertSet('note', 'Winter hours')
            ->assertSee('01 Nov 2026');
    });

    it('lets reception view hours but not change them', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Receptionist)->create());

        $this->get(route('schedules.edit', $this->doctor))->assertOk()->assertDontSee(__('Save hours'));
        Volt::test('schedules.edit', ['doctor' => $this->doctor])->call('save')->assertForbidden();
    });

    it('audits routine changes', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Manager)->create());
        $this->save->handle($this->doctor, '2026-11-01', routine());

        expect(Activity::where('log_name', 'doctor_schedule_blocks')->where('event', 'created')->count())->toBe(3)
            ->and(Activity::where('log_name', 'doctor_schedules')->exists())->toBeTrue();
    });
});
