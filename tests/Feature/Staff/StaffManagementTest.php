<?php

use App\Actions\Staff\SaveStaff;
use App\Enums\RoleName;
use App\Enums\StaffType;
use App\Models\Doctor;
use App\Models\Staff;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->artisan('app:sync-permissions');
    $this->manager = User::factory()->withRole(RoleName::Manager)->create();
});

describe('access', function () {
    it('lets a manager list, add and edit staff', function () {
        $this->actingAs($this->manager);
        $doctor = Doctor::factory()->create();

        $this->get(route('staff.index'))->assertOk()->assertSee($doctor->name)->assertSee(__('Add staff member'));
        $this->get(route('staff.create'))->assertOk();
        $this->get(route('staff.edit', $doctor->staff))->assertOk()->assertSee(__('Doctor details'));
    });

    it('lets reception see staff but not change them', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Receptionist)->create());

        $this->get(route('staff.index'))->assertOk()->assertDontSee(__('Add staff member'));
        $this->get(route('staff.create'))->assertForbidden();
    });

    it('hides staff from roles without staff.view', function (RoleName $role) {
        $this->actingAs(User::factory()->withRole($role)->create());

        $this->get(route('staff.index'))->assertForbidden();
    })->with([RoleName::Doctor, RoleName::Accountant, RoleName::InventoryStaff]);
});

describe('adding and editing', function () {
    it('adds a doctor with doctor details and a linked login', function () {
        $this->actingAs($this->manager);
        $login = User::factory()->create();

        Volt::test('staff.form')
            ->set('form.type', 'doctor')
            ->set('form.full_name', 'Dr. Nusrat Jahan')
            ->set('form.phone', '+880 1711-000000')
            ->set('form.user_id', (string) $login->id)
            ->set('form.doctor.registration_no', 'TEST-777')
            ->set('form.doctor.specialty', 'Orthodontics')
            ->set('form.doctor.default_slot_minutes', '30')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('staff.index'));

        $staff = Staff::where('full_name', 'Dr. Nusrat Jahan')->firstOrFail();
        expect($staff->type)->toBe(StaffType::Doctor)
            ->and($staff->phone)->toBe('01711000000')
            ->and($staff->user_id)->toBe($login->id)
            ->and($staff->doctor->registration_no)->toBe('TEST-777')
            ->and($staff->doctor->slotMinutes())->toBe(30)
            ->and($staff->created_by)->toBe($this->manager->id);
    });

    it('adds non-doctor staff without a doctor record', function () {
        $this->actingAs($this->manager);

        Volt::test('staff.form')
            ->set('form.full_name', 'Rumana Akter')
            ->set('form.designation', 'Dental assistant')
            ->call('save')
            ->assertHasNoErrors();

        expect(Staff::where('full_name', 'Rumana Akter')->firstOrFail()->doctor)->toBeNull();
    });

    it('turns a staff member into a doctor, but never a doctor back into staff', function () {
        $this->actingAs($this->manager);
        $staff = Staff::factory()->create();

        Volt::test('staff.form', ['staff' => $staff])
            ->set('form.type', 'doctor')
            ->call('save')
            ->assertHasNoErrors();
        expect($staff->fresh()->doctor)->not->toBeNull();

        Volt::test('staff.form', ['staff' => $staff->fresh()])
            ->set('form.type', 'staff')
            ->call('save')
            ->assertHasErrors(['form.type']);
        expect($staff->fresh()->type)->toBe(StaffType::Doctor);
    });

    it('edits doctor details without creating a second doctor record', function () {
        $this->actingAs($this->manager);
        $doctor = Doctor::factory()->create();

        Volt::test('staff.form', ['staff' => $doctor->staff])
            ->assertSet('form.doctor.registration_no', $doctor->registration_no)
            ->set('form.doctor.specialty', 'Endodontics')
            ->set('form.doctor.is_bookable', false)
            ->call('save')
            ->assertHasNoErrors();

        expect(Doctor::count())->toBe(1)
            ->and($doctor->fresh()->specialty)->toBe('Endodontics')
            ->and($doctor->fresh()->is_bookable)->toBeFalse();
    });

    it('validates phone, dates, duplicate BMDC numbers and logins', function () {
        $this->actingAs($this->manager);
        $other = Doctor::factory()->create(['registration_no' => 'TEST-1']);
        $takenLogin = Staff::factory()->withUser()->create()->user_id;

        Volt::test('staff.form')
            ->set('form.type', 'doctor')
            ->set('form.full_name', '')
            ->set('form.phone', '12345')
            ->set('form.joined_on', '2026-05-01')
            ->set('form.left_on', '2026-01-01')
            ->set('form.user_id', (string) $takenLogin)
            ->set('form.doctor.registration_no', 'TEST-1')
            ->set('form.doctor.calendar_color', 'red')
            ->call('save')
            ->assertHasErrors([
                'form.full_name', 'form.phone', 'form.left_on', 'form.user_id',
                'form.doctor.registration_no', 'form.doctor.calendar_color',
            ]);

        expect(Staff::count())->toBe(2);
    });

    it('only offers logins that are not linked to someone else', function () {
        $this->actingAs($this->manager);
        $free = User::factory()->create(['name' => 'Free Login']);
        Staff::factory()->create(['user_id' => User::factory()->create(['name' => 'Taken Login'])->id]);

        Volt::test('staff.form')->assertSee('Free Login')->assertDontSee('Taken Login');
    });

    it('deactivates a staff member who left, keeping the record', function () {
        $this->actingAs($this->manager);
        $staff = Staff::factory()->create();

        Volt::test('staff.form', ['staff' => $staff])
            ->set('form.is_active', false)
            ->set('form.left_on', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        expect($staff->fresh()->is_active)->toBeFalse();
        Volt::test('staff.index')->set('status', 'inactive')->assertSee($staff->full_name);
        Volt::test('staff.index')->assertDontSee($staff->full_name); // default list = active only
    });

    it('forbids saving without staff.manage even through the component', function () {
        $this->actingAs(User::factory()->withRole(RoleName::Receptionist)->create());

        Volt::test('staff.form')->assertForbidden();
    });
});

describe('doctor signature', function () {
    it('stores the signature privately and replaces the old file', function () {
        Storage::fake(SaveStaff::SIGNATURE_DISK);
        $this->actingAs($this->manager);
        $doctor = Doctor::factory()->create();

        Volt::test('staff.form', ['staff' => $doctor->staff])
            ->set('signature', UploadedFile::fake()->image('sign.png', 300, 100))
            ->call('save')
            ->assertHasNoErrors();
        $first = $doctor->fresh()->signature_path;
        Storage::disk(SaveStaff::SIGNATURE_DISK)->assertExists($first);
        expect($first)->toStartWith(SaveStaff::SIGNATURE_DIR.'/');

        Volt::test('staff.form', ['staff' => $doctor->staff->fresh()])
            ->set('signature', UploadedFile::fake()->image('new.png', 300, 100))
            ->call('save')
            ->assertHasNoErrors();
        Storage::disk(SaveStaff::SIGNATURE_DISK)->assertMissing($first);
        Storage::disk(SaveStaff::SIGNATURE_DISK)->assertExists($doctor->fresh()->signature_path);
    });

    it('rejects files that are not images', function () {
        Storage::fake(SaveStaff::SIGNATURE_DISK);
        $this->actingAs($this->manager);

        Volt::test('staff.form', ['staff' => Doctor::factory()->create()->staff])
            ->set('signature', UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'))
            ->call('save')
            ->assertHasErrors(['signature']);
    });
});

describe('listing', function () {
    it('finds staff by name, phone, specialty and BMDC number', function () {
        $this->actingAs($this->manager);
        $doctor = Doctor::factory()->create(['registration_no' => 'TEST-4242', 'specialty' => 'Periodontics']);
        Staff::factory()->create(['full_name' => 'Someone Else']);

        foreach ([$doctor->staff->full_name, $doctor->staff->phone, 'Periodontics', 'TEST-4242'] as $term) {
            Volt::test('staff.index')->set('search', $term)
                ->assertSee($doctor->staff->full_name)->assertDontSee('Someone Else');
        }
    });
});

it('normalises Bangladeshi mobile numbers', function (?string $input, ?string $expected) {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    ['01711000000', '01711000000'],
    ['+880 1711-000000', '01711000000'],
    ['8801711000000', '01711000000'],
    ['0171 100 0000', '01711000000'],
    ['', null],
    [null, null],
]);
