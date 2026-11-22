<?php

use App\Actions\Rooms\SaveChair;
use App\Actions\Rooms\SaveRoom;
use App\Models\Chair;
use App\Models\Room;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Rooms & chairs')] class extends Component {
    public string $newRoom = '';

    /** @var array<int, string> room id => new chair name */
    public array $newChair = [];

    /** What is being edited inline: ['type' => 'room'|'chair', 'id' => int, 'name' => ..., 'notes' => ..., 'sort_order' => ...] */
    public array $editing = [];

    public function addRoom(SaveRoom $action): void
    {
        Gate::authorize('staff.manage');
        $this->run(fn () => $action->handle(null, ['name' => $this->newRoom]), 'newRoom');
        $this->reset('newRoom');
    }

    public function addChair(int $roomId, SaveChair $action): void
    {
        Gate::authorize('staff.manage');
        $room = Room::findOrFail($roomId);
        $this->run(fn () => $action->handle($room, null, ['name' => $this->newChair[$roomId] ?? '']), "newChair.{$roomId}");
        unset($this->newChair[$roomId]);
    }

    public function edit(string $type, int $id): void
    {
        Gate::authorize('staff.manage');
        $model = $type === 'room' ? Room::findOrFail($id) : Chair::findOrFail($id);

        $this->resetErrorBag();
        $this->editing = [
            'type' => $type,
            'id' => $id,
            'name' => $model->name,
            'notes' => $model instanceof Room ? (string) $model->notes : '',
            'sort_order' => $model->sort_order,
        ];
    }

    public function saveEdit(SaveRoom $saveRoom, SaveChair $saveChair): void
    {
        Gate::authorize('staff.manage');
        $input = collect($this->editing)->only(['name', 'notes', 'sort_order'])->all();

        $saved = $this->run(function () use ($input, $saveRoom, $saveChair) {
            if ($this->editing['type'] === 'room') {
                return $saveRoom->handle(Room::findOrFail($this->editing['id']), $input);
            }
            $chair = Chair::findOrFail($this->editing['id']);

            return $saveChair->handle($chair->room, $chair, $input);
        }, 'editing');

        if ($saved) {
            $this->editing = [];
        }
    }

    public function cancelEdit(): void
    {
        $this->editing = [];
        $this->resetErrorBag();
    }

    public function setActive(string $type, int $id, bool $active, SaveRoom $saveRoom, SaveChair $saveChair): void
    {
        Gate::authorize('staff.manage');

        if ($type === 'room') {
            $room = Room::findOrFail($id);
            $saveRoom->handle($room, ['name' => $room->name, 'notes' => $room->notes, 'is_active' => $active]);
        } else {
            $chair = Chair::findOrFail($id);
            $saveChair->handle($chair->room, $chair, ['name' => $chair->name, 'is_active' => $active]);
        }
    }

    /** Run an action and show its validation errors next to the given field. */
    private function run(callable $callback, string $errorKey): mixed
    {
        $this->resetErrorBag();

        try {
            return $callback();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $key = $errorKey === 'editing' ? "editing.{$field}" : $errorKey;
                $this->addError($key, $messages[0]);
            }

            return null;
        }
    }

    public function with(): array
    {
        return [
            'rooms' => Room::query()->with('chairs')->orderBy('sort_order')->orderBy('name')->get(),
            'canManage' => Gate::allows('staff.manage'),
        ];
    }
}; ?>

<div class="flex max-w-4xl flex-col gap-6 p-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Rooms & chairs') }}</flux:heading>
        <flux:subheading>{{ __('Dental chairs appear in the appointment calendar. Deactivate instead of deleting — past appointments keep their room and chair.') }}</flux:subheading>
    </div>

    @if ($canManage)
        <form wire:submit="addRoom" class="flex items-start gap-2">
            <flux:input wire:model="newRoom" :placeholder="__('New room name, e.g. Room 1')" class="max-w-xs" />
            <flux:button type="submit" icon="plus">{{ __('Add room') }}</flux:button>
        </form>
        <flux:error name="newRoom" />
    @endif

    @forelse ($rooms as $room)
        <flux:card wire:key="room-{{ $room->id }}" @class(['flex flex-col gap-4', 'opacity-60' => ! $room->is_active])>
            {{-- Room header --}}
            @if (($editing['type'] ?? null) === 'room' && $editing['id'] === $room->id)
                <form wire:submit="saveEdit" class="grid gap-3 sm:grid-cols-[1fr_1fr_6rem_auto]">
                    <flux:input wire:model="editing.name" :label="__('Room name')" />
                    <flux:input wire:model="editing.notes" :label="__('Notes')" />
                    <flux:input wire:model="editing.sort_order" type="number" min="0" :label="__('Order')" />
                    <div class="flex items-end gap-2">
                        <flux:button type="submit" variant="primary" size="sm">{{ __('Save') }}</flux:button>
                        <flux:button type="button" variant="ghost" size="sm" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            @else
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <flux:heading size="lg" class="flex items-center gap-2">
                            {{ $room->name }}
                            @unless ($room->is_active) <flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge> @endunless
                        </flux:heading>
                        @if ($room->notes) <flux:text class="text-sm">{{ $room->notes }}</flux:text> @endif
                    </div>
                    @if ($canManage)
                        <div class="flex gap-1">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit('room', {{ $room->id }})">{{ __('Edit') }}</flux:button>
                            @if ($room->is_active)
                                <flux:button size="sm" variant="ghost" icon="no-symbol" wire:click="setActive('room', {{ $room->id }}, false)"
                                    wire:confirm="{{ __('Deactivate :room? Its chairs will no longer be offered for booking.', ['room' => $room->name]) }}">{{ __('Deactivate') }}</flux:button>
                            @else
                                <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="setActive('room', {{ $room->id }}, true)">{{ __('Reactivate') }}</flux:button>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            {{-- Chairs --}}
            <ul class="divide-y divide-zinc-100 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                @forelse ($room->chairs as $chair)
                    <li wire:key="chair-{{ $chair->id }}" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                        @if (($editing['type'] ?? null) === 'chair' && $editing['id'] === $chair->id)
                            <form wire:submit="saveEdit" class="flex w-full flex-wrap items-start gap-2">
                                <flux:input wire:model="editing.name" size="sm" class="max-w-xs" />
                                <flux:input wire:model="editing.sort_order" type="number" min="0" size="sm" class="max-w-20" :aria-label="__('Order')" />
                                <flux:button type="submit" variant="primary" size="sm">{{ __('Save') }}</flux:button>
                                <flux:button type="button" variant="ghost" size="sm" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                            </form>
                        @else
                            <span @class(['flex items-center gap-2', 'text-zinc-400 line-through' => ! $chair->is_active])>
                                <flux:icon name="square-3-stack-3d" class="size-4 text-zinc-400" />
                                {{ $chair->name }}
                            </span>
                            @if ($canManage)
                                <div class="flex gap-1">
                                    <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit('chair', {{ $chair->id }})">{{ __('Edit') }}</flux:button>
                                    @if ($chair->is_active)
                                        <flux:button size="xs" variant="ghost" icon="no-symbol" wire:click="setActive('chair', {{ $chair->id }}, false)">{{ __('Deactivate') }}</flux:button>
                                    @else
                                        <flux:button size="xs" variant="ghost" icon="arrow-path" wire:click="setActive('chair', {{ $chair->id }}, true)">{{ __('Reactivate') }}</flux:button>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </li>
                @empty
                    <li class="px-3 py-2 text-sm text-zinc-500">{{ __('No chairs yet.') }}</li>
                @endforelse
            </ul>
            @if (($editing['type'] ?? null) !== null)
                <flux:error name="editing.name" />
                <flux:error name="editing.sort_order" />
            @endif

            @if ($canManage && $room->is_active)
                <form wire:submit="addChair({{ $room->id }})" class="flex items-start gap-2">
                    <flux:input wire:model="newChair.{{ $room->id }}" size="sm" :placeholder="__('New chair, e.g. Chair A')" class="max-w-xs" />
                    <flux:button type="submit" size="sm" icon="plus">{{ __('Add chair') }}</flux:button>
                </form>
                <flux:error name="newChair.{{ $room->id }}" />
            @endif
        </flux:card>
    @empty
        <flux:callout icon="information-circle" :heading="__('No rooms yet. Add the clinic\'s treatment rooms, then the dental chairs in each.')" />
    @endforelse
</div>
