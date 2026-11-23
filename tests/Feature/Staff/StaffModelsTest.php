<?php

use App\Enums\StaffType;
use App\Models\Chair;
use App\Models\Doctor;
use App\Models\Room;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

describe('staff and doctors', function () {
    it('links a doctor to a staff record and a login account', function () {
        $doctor = Doctor::factory()->create(['staff_id' => Staff::factory()->doctor()->withUser()]);

        expect($doctor->staff->type)->toBe(StaffType::Doctor)
            ->and($doctor->staff->isDoctor())->toBeTrue()
            ->and($doctor->name)->toStartWith('Dr. ')
            ->and($doctor->staff->user)->toBeInstanceOf(User::class)
            ->and($doctor->staff->user->staff->is($doctor->staff))->toBeTrue()
            ->and($doctor->staff->doctor->is($doctor))->toBeTrue();
    });

    it('lets one login belong to only one staff record', function () {
        $user = User::factory()->create();
        Staff::factory()->create(['user_id' => $user->id]);

        Staff::factory()->create(['user_id' => $user->id]);
    })->throws(QueryException::class);

    it('keeps BMDC registration numbers unique', function () {
        Doctor::factory()->create(['registration_no' => 'A-1234']);

        Doctor::factory()->create(['registration_no' => 'A-1234']);
    })->throws(QueryException::class);

    it('allows several doctors without a registration number yet', function () {
        Doctor::factory()->count(2)->create(['registration_no' => null]);

        expect(Doctor::whereNull('registration_no')->count())->toBe(2);
    });

    it('uses the clinic default slot length unless the doctor has their own', function () {
        $usesDefault = Doctor::factory()->create();
        $ownLength = Doctor::factory()->create(['default_slot_minutes' => 40]);

        expect($usesDefault->slotMinutes())->toBe(15)
            ->and($ownLength->slotMinutes())->toBe(40);

        app(App\Settings\Settings::class)->setMany(['appointments.default_slot_minutes' => 20]);
        expect($usesDefault->slotMinutes())->toBe(20);
    });

    it('only offers bookable doctors who are still active', function () {
        $available = Doctor::factory()->create();
        Doctor::factory()->notBookable()->create();
        Doctor::factory()->create(['staff_id' => Staff::factory()->doctor()->inactive()]);

        expect(Doctor::bookable()->pluck('id')->all())->toBe([$available->id]);
    });

    it('refuses to delete a staff member who has a doctor record', function () {
        $doctor = Doctor::factory()->create();

        $doctor->staff->delete();
    })->throws(QueryException::class);
});

describe('rooms and chairs', function () {
    it('labels chairs with their room and lists them in order', function () {
        $room = Room::factory()->create(['name' => 'Room 1']);
        Chair::factory()->for($room)->create(['name' => 'Chair B', 'sort_order' => 2]);
        Chair::factory()->for($room)->create(['name' => 'Chair A', 'sort_order' => 1]);

        expect($room->chairs->pluck('label')->all())->toBe(['Room 1 · Chair A', 'Room 1 · Chair B']);
    });

    it('does not allow two chairs with the same name in one room', function () {
        $room = Room::factory()->create();
        Chair::factory()->for($room)->create(['name' => 'Chair A']);

        Chair::factory()->for($room)->create(['name' => 'Chair A']);
    })->throws(QueryException::class);

    it('allows the same chair name in different rooms', function () {
        Chair::factory()->create(['name' => 'Chair A']);
        Chair::factory()->create(['name' => 'Chair A']);

        expect(Chair::where('name', 'Chair A')->count())->toBe(2);
    });

    it('treats a chair in an inactive room as unusable', function () {
        $usable = Chair::factory()->create();
        Chair::factory()->inactive()->create();
        Chair::factory()->for(Room::factory()->inactive())->create();

        expect(Chair::usable()->pluck('id')->all())->toBe([$usable->id]);
    });

    it('refuses to delete a room that still has chairs', function () {
        $chair = Chair::factory()->create();

        $chair->room->delete();
    })->throws(QueryException::class);
});

it('records who created and changed staff, doctors, rooms and chairs', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $doctor = Doctor::factory()->create();
    $doctor->update(['specialty' => 'Orthodontics']);
    $chair = Chair::factory()->create();

    foreach ([$doctor, $doctor->staff, $chair, $chair->room] as $model) {
        expect($model->fresh()->created_by)->toBe($admin->id);
    }

    expect(Activity::where('log_name', 'doctors')->where('event', 'updated')->first()->attribute_changes['attributes'])
        ->toBe(['specialty' => 'Orthodontics'])
        ->and(Activity::whereIn('log_name', ['staff', 'rooms', 'chairs'])->where('event', 'created')->count())->toBe(3);
});
