<?php

use App\Enums\RoleName;
use App\Models\Chair;
use App\Models\Room;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->artisan('app:sync-permissions');
    $this->manager = User::factory()->withRole(RoleName::Manager)->create();
});

it('lets a manager add rooms and chairs', function () {
    $this->actingAs($this->manager);

    $page = Volt::test('rooms.index')
        ->set('newRoom', '  Room 1 ')
        ->call('addRoom')
        ->assertHasNoErrors();

    $room = Room::where('name', 'Room 1')->firstOrFail();

    $page->set("newChair.{$room->id}", 'Chair A')->call('addChair', $room->id)->assertHasNoErrors()
        ->set("newChair.{$room->id}", 'Chair B')->call('addChair', $room->id)->assertHasNoErrors()
        ->assertSee('Chair A')->assertSee('Chair B');

    expect($room->chairs->pluck('name')->all())->toBe(['Chair A', 'Chair B'])
        ->and($room->chairs->pluck('sort_order')->all())->toBe([1, 2])
        ->and($room->created_by)->toBe($this->manager->id);
});

it('rejects duplicate room names and duplicate chair names within a room', function () {
    $this->actingAs($this->manager);
    $room = Room::factory()->create(['name' => 'Room 1']);
    Chair::factory()->for($room)->create(['name' => 'Chair A']);

    Volt::test('rooms.index')
        ->set('newRoom', 'Room 1')->call('addRoom')->assertHasErrors(['newRoom'])
        ->set("newChair.{$room->id}", 'Chair A')->call('addChair', $room->id)->assertHasErrors(["newChair.{$room->id}"])
        ->set('newRoom', '')->call('addRoom')->assertHasErrors(['newRoom']);

    expect(Room::count())->toBe(1)->and(Chair::count())->toBe(1);
});

it('renames a room and a chair inline', function () {
    $this->actingAs($this->manager);
    $chair = Chair::factory()->create(['name' => 'Chair A']);

    Volt::test('rooms.index')
        ->call('edit', 'room', $chair->room_id)
        ->set('editing.name', 'Surgery room')
        ->set('editing.notes', 'Ground floor')
        ->call('saveEdit')
        ->assertHasNoErrors()
        ->assertSet('editing', [])
        ->call('edit', 'chair', $chair->id)
        ->set('editing.name', 'Chair 1')
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($chair->fresh()->name)->toBe('Chair 1')
        ->and($chair->room->fresh()->name)->toBe('Surgery room')
        ->and($chair->room->fresh()->notes)->toBe('Ground floor');
});

it('keeps the edit open and shows the error when a rename clashes', function () {
    $this->actingAs($this->manager);
    $room = Room::factory()->create();
    Chair::factory()->for($room)->create(['name' => 'Chair A']);
    $b = Chair::factory()->for($room)->create(['name' => 'Chair B']);

    Volt::test('rooms.index')
        ->call('edit', 'chair', $b->id)
        ->set('editing.name', 'Chair A')
        ->call('saveEdit')
        ->assertHasErrors(['editing.name'])
        ->assertSet('editing.id', $b->id);

    expect($b->fresh()->name)->toBe('Chair B');
});

it('deactivates a room so its chairs are no longer usable, and reactivates it', function () {
    $this->actingAs($this->manager);
    $chair = Chair::factory()->create();

    Volt::test('rooms.index')->call('setActive', 'room', $chair->room_id, false);
    expect($chair->room->fresh()->is_active)->toBeFalse()
        ->and(Chair::usable()->count())->toBe(0)
        ->and($chair->fresh()->is_active)->toBeTrue(); // the chair itself keeps its own setting

    Volt::test('rooms.index')->call('setActive', 'room', $chair->room_id, true);
    expect(Chair::usable()->count())->toBe(1);
});

it('deactivates a single chair', function () {
    $this->actingAs($this->manager);
    $chair = Chair::factory()->create();

    Volt::test('rooms.index')->call('setActive', 'chair', $chair->id, false);

    expect($chair->fresh()->is_active)->toBeFalse();
});

it('lets reception view rooms but not change them', function () {
    $this->actingAs(User::factory()->withRole(RoleName::Receptionist)->create());
    $room = Room::factory()->create(['name' => 'Room 9']);

    $this->get(route('rooms.index'))->assertOk()->assertSee('Room 9')->assertDontSee(__('Add room'));

    Volt::test('rooms.index')->set('newRoom', 'Sneaky')->call('addRoom')->assertForbidden();
    Volt::test('rooms.index')->call('setActive', 'room', $room->id, false)->assertForbidden();
    expect($room->fresh()->is_active)->toBeTrue();
});
