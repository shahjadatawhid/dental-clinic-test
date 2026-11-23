<?php

use App\Models\Chair;
use App\Models\ClinicHoliday;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorTimeOff;
use App\Models\Room;
use App\Models\Staff;
use App\Models\User;
use App\Services\DoctorAvailability;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

beforeEach(fn () => Carbon::setTestNow('2026-11-22 10:00'));
afterEach(fn () => Carbon::setTestNow());

it('creates a usable demo clinic and is safe to run twice', function () {
    $this->seed();
    $this->seed();

    expect(Room::count())->toBe(3)
        ->and(Chair::usable()->count())->toBe(4)
        ->and(Doctor::bookable()->count())->toBe(4)
        ->and(Staff::count())->toBe(7)
        ->and(DoctorSchedule::count())->toBe(4)
        ->and(DoctorTimeOff::count())->toBe(2)
        ->and(ClinicHoliday::count())->toBe(12);
});

it('gives every demo doctor working hours somewhere in the week', function () {
    $this->seed();
    $availability = app(DoctorAvailability::class);

    foreach (Doctor::all() as $doctor) {
        $hours = collect(range(0, 6))->sum(fn ($i) => count($availability->workingPeriods($doctor, now()->addWeeks(2)->startOfWeek(CarbonInterface::SATURDAY)->addDays($i))));
        expect($hours)->toBeGreaterThan(0, "{$doctor->name} has no hours");
    }
});

it('links the demo doctor and receptionist logins to their staff records', function () {
    $this->seed();

    expect(User::where('email', 'doctor@clinic.test')->first()->staff->doctor->name)->toBe('Dr. Nusrat Jahan')
        ->and(User::where('email', 'receptionist@clinic.test')->first()->staff->full_name)->toBe('Rumana Akter');
});

it('puts the demo leave on next weekend', function () {
    $this->seed();
    $rakib = Doctor::where('registration_no', 'TEST-0002')->first();

    expect(app(DoctorAvailability::class)->isOffAllDay($rakib, Carbon::parse('2026-11-28')))->toBeTrue()  // next Saturday
        ->and(app(DoctorAvailability::class)->workingPeriods($rakib, Carbon::parse('2026-12-05')))->not->toBeEmpty();
});
