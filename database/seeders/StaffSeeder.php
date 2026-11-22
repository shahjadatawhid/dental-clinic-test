<?php

namespace Database\Seeders;

use App\Actions\Schedules\SaveDoctorSchedule;
use App\Enums\StaffType;
use App\Enums\TimeOffType;
use App\Models\Chair;
use App\Models\ClinicHoliday;
use App\Models\Doctor;
use App\Models\DoctorTimeOff;
use App\Models\Room;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonInterface as Day;
use Illuminate\Database\Seeder;

/**
 * M02 demo data: rooms, chairs, fictional doctors with weekly routines, other staff,
 * some leave and the fixed-date national holidays. Safe to run again.
 */
class StaffSeeder extends Seeder
{
    use GuardsAgainstProduction;

    private const ROOMS = [
        'Room 1' => ['Chair A', 'Chair B'],
        'Room 2' => ['Chair C'],
        'Surgery room' => ['Surgery chair'],
    ];

    public function run(): void
    {
        $this->refuseInProduction();

        $chairs = $this->rooms();
        $doctors = $this->doctors();
        $this->otherStaff();
        $this->routines($doctors, $chairs);
        $this->timeOff($doctors);
        $this->holidays();
    }

    /** @return array<string, Chair> chair name => chair */
    private function rooms(): array
    {
        $chairs = [];
        $order = 1;

        foreach (self::ROOMS as $roomName => $chairNames) {
            $room = Room::firstOrCreate(['name' => $roomName], ['sort_order' => $order++]);
            foreach ($chairNames as $i => $chairName) {
                $chairs[$chairName] = Chair::firstOrCreate(
                    ['room_id' => $room->id, 'name' => $chairName],
                    ['sort_order' => $i + 1],
                );
            }
        }

        return $chairs;
    }

    /** @return array<string, Doctor> key => doctor */
    private function doctors(): array
    {
        $list = [
            'nusrat' => ['Dr. Nusrat Jahan', 'female', 'TEST-0001', 'General dentistry', 'BDS', '#0ea5e9', 'doctor@clinic.test'],
            'rakib' => ['Dr. Rakib Hossain', 'male', 'TEST-0002', 'Orthodontics', 'BDS, FCPS (Orthodontics)', '#22c55e', null],
            'farzana' => ['Dr. Farzana Akter', 'female', 'TEST-0003', 'Endodontics', 'BDS, MS (Conservative Dentistry)', '#a855f7', null],
            'tanvir' => ['Dr. Tanvir Ahmed', 'male', 'TEST-0004', 'Oral & maxillofacial surgery', 'BDS, FCPS (OMS)', '#ef4444', null],
        ];

        $doctors = [];
        foreach ($list as $key => [$name, $gender, $reg, $specialty, $qualifications, $color, $login]) {
            $doctor = Doctor::where('registration_no', $reg)->first();

            if ($doctor === null) {
                $staff = Staff::create([
                    'full_name' => $name,
                    'type' => StaffType::Doctor,
                    'designation' => 'Dental surgeon',
                    'gender' => $gender,
                    'phone' => fake_bd()->phone(),
                    'joined_on' => now()->subYears(2)->startOfYear(),
                    'user_id' => $this->freeLogin($login),
                ]);
                $doctor = Doctor::create([
                    'staff_id' => $staff->id,
                    'registration_no' => $reg,
                    'specialty' => $specialty,
                    'qualifications' => $qualifications,
                    'calendar_color' => $color,
                    'default_slot_minutes' => $key === 'tanvir' ? 45 : null,
                ]);
            }

            $doctors[$key] = $doctor;
        }

        return $doctors;
    }

    private function otherStaff(): void
    {
        $list = [
            ['Rumana Akter', 'female', 'Receptionist', 'receptionist@clinic.test'],
            ['Sohel Rana', 'male', 'Dental assistant', null],
            ['Shapla Begum', 'female', 'Nurse', null],
        ];

        foreach ($list as [$name, $gender, $designation, $login]) {
            Staff::firstOrCreate(
                ['full_name' => $name, 'type' => StaffType::Staff],
                [
                    'designation' => $designation,
                    'gender' => $gender,
                    'phone' => fake_bd()->phone(),
                    'joined_on' => now()->subYear()->startOfYear(),
                    'user_id' => $this->freeLogin($login),
                ],
            );
        }
    }

    /**
     * @param  array<string, Doctor>  $doctors
     * @param  array<string, Chair>  $chairs
     */
    private function routines(array $doctors, array $chairs): void
    {
        $from = now()->startOfMonth()->toDateString();
        $satToThu = [Day::SATURDAY, Day::SUNDAY, Day::MONDAY, Day::TUESDAY, Day::WEDNESDAY, Day::THURSDAY];
        $block = fn (int $day, string $start, string $end, string $chair) => [
            'weekday' => $day, 'start_time' => $start, 'end_time' => $end, 'chair_id' => $chairs[$chair]->id,
        ];

        $routines = [
            'nusrat' => array_map(fn ($d) => $block($d, '17:00', '21:00', 'Chair A'), $satToThu),
            'rakib' => [
                ...array_map(fn ($d) => $block($d, '10:00', '13:00', 'Chair B'), [Day::SATURDAY, Day::MONDAY, Day::WEDNESDAY]),
                ...array_map(fn ($d) => $block($d, '17:00', '20:00', 'Chair B'), [Day::SATURDAY, Day::MONDAY, Day::WEDNESDAY]),
            ],
            'farzana' => array_map(fn ($d) => $block($d, '10:00', '14:00', 'Chair C'), [Day::SUNDAY, Day::TUESDAY, Day::THURSDAY]),
            'tanvir' => [$block(Day::THURSDAY, '15:00', '20:00', 'Surgery chair')],
        ];

        foreach ($routines as $key => $blocks) {
            if (! $doctors[$key]->schedules()->exists()) {
                app(SaveDoctorSchedule::class)->handle($doctors[$key], $from, $blocks);
            }
        }
    }

    /** @param  array<string, Doctor>  $doctors */
    private function timeOff(array $doctors): void
    {
        if (DoctorTimeOff::exists()) {
            return;
        }

        $nextSaturday = now()->next(Day::SATURDAY);

        DoctorTimeOff::create([
            'doctor_id' => $doctors['rakib']->id,
            'type' => TimeOffType::Leave,
            'starts_at' => $nextSaturday->copy()->startOfDay(),
            'ends_at' => $nextSaturday->copy()->addDays(2)->startOfDay(), // Sat + Sun
            'reason' => 'Conference (demo)',
        ]);

        $nextThursday = now()->next(Day::THURSDAY);
        DoctorTimeOff::create([
            'doctor_id' => $doctors['nusrat']->id,
            'type' => TimeOffType::Blocked,
            'starts_at' => $nextThursday->copy()->setTime(18, 0),
            'ends_at' => $nextThursday->copy()->setTime(19, 0),
            'reason' => 'Staff meeting (demo)',
        ]);
    }

    /** Bangladeshi holidays with fixed dates. Eid and other lunar holidays change every year — add them on the Holidays screen. */
    private function holidays(): void
    {
        $fixed = [
            '02-21' => 'Shaheed Day & International Mother Language Day',
            '03-26' => 'Independence Day',
            '04-14' => 'Pohela Boishakh',
            '05-01' => 'May Day',
            '12-16' => 'Victory Day',
            '12-25' => 'Christmas Day',
        ];

        foreach ([now()->year, now()->year + 1] as $year) {
            foreach ($fixed as $monthDay => $name) {
                ClinicHoliday::firstOrCreate(['date' => "{$year}-{$monthDay}"], ['name' => $name]);
            }
        }
    }

    /** The user with this email, if it exists and isn't linked to anyone yet. */
    private function freeLogin(?string $email): ?int
    {
        if ($email === null) {
            return null;
        }

        return User::where('email', $email)->whereDoesntHave('staff')->value('id');
    }
}
