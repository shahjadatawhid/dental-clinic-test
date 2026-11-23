<?php

namespace App\Actions\Rooms;

use App\Models\Chair;
use App\Models\Room;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveChair
{
    /**
     * @param  array{name?: string, sort_order?: int|string|null, is_active?: bool}  $input
     */
    public function handle(Room $room, ?Chair $chair, array $input): Chair
    {
        $input['name'] = trim((string) ($input['name'] ?? ''));

        $data = Validator::make($input, [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('chairs', 'name')->where('room_id', $room->id)->ignore($chair?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ], ['name.unique' => __('This room already has a chair with this name.')])->validate();

        $isNew = $chair === null;
        $chair ??= new Chair(['room_id' => $room->id]);
        // New chairs go to the end of the room's list unless an order is given.
        $defaultOrder = $isNew ? ((int) $room->chairs()->max('sort_order')) + 1 : $chair->sort_order;

        $chair->fill([
            'name' => $data['name'],
            'sort_order' => ($data['sort_order'] ?? '') !== '' ? (int) $data['sort_order'] : $defaultOrder,
            'is_active' => (bool) ($data['is_active'] ?? $chair->is_active ?? true),
        ])->save();

        return $chair;
    }
}
