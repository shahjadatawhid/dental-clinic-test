<?php

use App\Actions\Schedules\AddClinicHolidays;
use App\Actions\Schedules\AddTimeOff;
use App\Actions\Schedules\SaveDoctorSchedule;
use App\Enums\RoleName;
use App\Enums\TimeOffType;
use App\Models\Chair;
use App\Models\ClinicHoliday;
use App\Models\Doctor;
use App\Models\DoctorTimeOff;
use App\Models\User;
use App\Services\DoctorAvailability;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->artisan('app:sync-permissions');
    Carbon::setTestNow('2026-11-22 10:00'); // Sunday

    $this->doctor = Doctor::factory()->create();
    // Saturdays and Sundays: 10:00–13:00 and 17:00–21:00
    app(SaveDoctorSchedule::class)->handle($this->doctor, '2026-11-01', [
        ['weekday' => CarbonInterface::SATURDAY, 'start_time' => '10:00', 'end_time' => '13:00'],
        ['weekday' => CarbonInterface::SATURDAY, 'start_time' => '17:00', 'end_time' => '21:00'],
        ['weekday' => CarbonInterface::SUNDAY, 'start_time' => '17:00', 'end_time' => '21:00'],
    ]);

    $this->availability = app(DoctorAvailability::class);
    $this->addOff = app(AddTimeOff::class);
});

afterEach(fn () => Carbon::setTestNow());

/** @return list<string> e.g. ['10:00–13:00'] */
function periods(Doctor $doctor, string $date): array
{
    return array_map(
        fn ($p) => $p['start']->format('H:i').'–'.$p['end']->format('H:i'),
        app(DoctorAvailability::class)->workingPeriods($doctor, Carbon::parse($date)),
    );
}

describe('working periods', function () {
    it('equals the routine on a normal day', function () {
        expect(periods($this->doctor, '2026-11-28'))->toBe(['10:00–13:00', '17:00–21:00'])
            ->and(periods($this->doctor, '2026-11-27'))->toBe([]); // Friday: not in routine
    });

    it('is empty on a clinic holiday', function () {
        ClinicHoliday::create(['date' => '2026-11-28', 'name' => 'Test holiday']);

        expect(periods($this->doctor, '2026-11-28'))->toBe([])
            ->and(periods($this->doctor, '2026-11-29'))->toBe(['17:00–21:00']);
    });

    it('is empty on every day of a leave, and normal again after it', function () {
        $this->addOff->handle($this->doctor, ['type' => 'leave', 'from_date' => '2026-11-28', 'to_date' => '2026-11-29']);

        expect(periods($this->doctor, '2026-11-28'))->toBe([])
            ->and(periods($this->doctor, '2026-11-29'))->toBe([])
            ->and(periods($this->doctor, '2026-12-05'))->toBe(['10:00–13:00', '17:00–21:00'])
            ->and($this->availability->isOffAllDay($this->doctor, Carbon::parse('2026-11-28')))->toBeTrue();
    });

    it('cuts blocked time out of the middle, start and end of a period', function () {
        $this->addOff->handle($this->doctor, ['type' => 'blocked', 'from_date' => '2026-11-28', 'start_time' => '11:00', 'end_time' => '12:00']);
        $this->addOff->handle($this->doctor, ['type' => 'blocked', 'from_date' => '2026-11-28', 'start_time' => '16:00', 'end_time' => '18:00']);
        $this->addOff->handle($this->doctor, ['type' => 'blocked', 'from_date' => '2026-11-28', 'start_time' => '20:30', 'end_time' => '22:00']);

        expect(periods($this->doctor, '2026-11-28'))->toBe(['10:00–11:00', '12:00–13:00', '18:00–20:30'])
            ->and($this->availability->isOffAllDay($this->doctor, Carbon::parse('2026-11-28')))->toBeFalse();
    });

    it('ignores another doctor\'s leave', function () {
        $this->addOff->handle(Doctor::factory()->create(), ['type' => 'leave', 'from_date' => '2026-11-28']);

        expect(periods($this->doctor, '2026-11-28'))->toBe(['10:00–13:00', '17:00–21:00']);
    });

    it('keeps the chair of each period', function () {
        $chair = Chair::factory()->create();
        app(SaveDoctorSchedule::class)->handle($this->doctor, '2026-12-01', [
            ['weekday' => CarbonInterface::SATURDAY, 'start_time' => '10:00', 'end_time' => '13:00', 'chair_id' => $chair->id],
        ]);
        $this->addOff->handle($this->doctor, ['type' => 'blocked', 'from_date' => '2026-12-05', 'start_time' => '11:00', 'end_time' => '12:00']);

        $result = $this->availability->workingPeriods($this->doctor, Carbon::parse('2026-12-05'));
        expect(array_column($result, 'chair_id'))->toBe([$chair->id, $chair->id]);
    });
});

describe('recording time off', function () {
    it('stores leave as whole days with an exclusive end', function () {
        $leave = $this->addOff->handle($this->doctor, ['type' => 'leave', 'from_date' => '2026-12-03', 'to_date' => '2026-12-05', 'reason' => 'Conference']);

        expect($leave->starts_at->toDateTimeString())->toBe('2026-12-03 00:00:00')
            ->and($leave->ends_at->toDateTimeString())->toBe('2026-12-06 00:00:00')
            ->and($leave->isAllDay())->toBeTrue()
            ->and($leave->describe())->toBe('3 Dec – 5 Dec 2026');
    });

    it('treats leave without a last day as one day', function () {
        $leave = $this->addOff->handle($this->doctor, ['type' => 'leave', 'from_date' => '2026-12-03']);

        expect($leave->ends_at->toDateString())->toBe('2026-12-04')
            ->and($leave->describe())->toBe('Thu 3 Dec 2026');
    });

    it('rejects bad input', function (array $input) {
        expect(fn () => $this->addOff->handle($this->doctor, $input))->toThrow(ValidationException::class);
    })->with([
        'leave ending before it starts' => [['type' => 'leave', 'from_date' => '2026-12-05', 'to_date' => '2026-12-03']],
        'blocked time ending before it starts' => [['type' => 'blocked', 'from_date' => '2026-12-05', 'start_time' => '12:00', 'end_time' => '11:00']],
        'blocked time without times' => [['type' => 'blocked', 'from_date' => '2026-12-05']],
        'unknown type' => [['type' => 'holiday', 'from_date' => '2026-12-05']],
        'missing date' => [['type' => 'leave']],
    ]);
});

describe('clinic holidays', function () {
    it('adds one row per day of a range and skips days already entered', function () {
        ClinicHoliday::create(['date' => '2027-03-21', 'name' => 'Already there']);

        $added = app(AddClinicHolidays::class)->handle(['name' => 'Eid-ul-Fitr', 'from_date' => '2027-03-20', 'to_date' => '2027-03-22']);

        expect($added)->toBe(2)
            ->and(ClinicHoliday::orderBy('date')->pluck('name')->all())->toBe(['Eid-ul-Fitr', 'Already there', 'Eid-ul-Fitr']);
    });

    it('refuses ranges longer than a month', function () {
        app(AddClinicHolidays::class)->handle(['name' => 'Too long', 'from_date' => '2027-01-01', 'to_date' => '2027-03-01']);
    })->throws(ValidationException::class);
});

describe('screens', function () {
    it('lets a manager add and remove leave from the doctor\'s hours page', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Manager)->create());

        Volt::test('schedules.edit', ['doctor' => $this->doctor])
            ->set('off.type', 'leave')
            ->set('off.from_date', '2026-12-03')
            ->set('off.to_date', '2026-12-04')
            ->set('off.reason', 'Family')
            ->call('addTimeOff')
            ->assertHasNoErrors()
            ->assertSee('3 Dec – 4 Dec 2026')
            ->assertSee('Family');

        $entry = DoctorTimeOff::firstOrFail();
        expect($entry->type)->toBe(TimeOffType::Leave);

        Volt::test('schedules.edit', ['doctor' => $this->doctor])->call('removeTimeOff', $entry->id);
        expect(DoctorTimeOff::count())->toBe(0);
    });

    it('shows time-off errors under the form', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Manager)->create());

        Volt::test('schedules.edit', ['doctor' => $this->doctor])
            ->set('off.type', 'blocked')
            ->set('off.from_date', '2026-12-03')
            ->set('off.start_time', '12:00')
            ->set('off.end_time', '11:00')
            ->call('addTimeOff')
            ->assertHasErrors(['off.end_time']);
    });

    it('flags doctors on leave today and a clinic holiday today', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Receptionist)->create());
        $this->addOff->handle($this->doctor, ['type' => 'leave', 'from_date' => '2026-11-22']);
        ClinicHoliday::create(['date' => '2026-11-22', 'name' => 'Test closure']);

        $this->get(route('schedules.index'))->assertOk()
            ->assertSee(__('On leave today'))
            ->assertSee('Test closure');
    });

    it('lets a manager manage holidays and reception only view them', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Manager)->create());
        Volt::test('schedules.holidays')
            ->set('name', 'Victory Day')
            ->set('fromDate', '2026-12-16')
            ->call('add')
            ->assertHasNoErrors()
            ->assertSee('Victory Day');

        $this->actingAs(User::factory()->withRole(RoleName::Receptionist)->create());
        $this->get(route('holidays.index'))->assertOk()->assertSee('Victory Day')->assertDontSee(__('Add holiday'));
        Volt::test('schedules.holidays')->call('remove', ClinicHoliday::first()->id)->assertForbidden();
        Volt::test('schedules.edit', ['doctor' => $this->doctor])->call('addTimeOff')->assertForbidden();
    });
});
