<?php

namespace App\Actions\Rooms;

use App\Models\Room;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveRoom
{
    /**
     * @param  array{name?: string, notes?: string|null, sort_order?: int|string|null, is_active?: bool}  $input
     */
    public function handle(?Room $room, array $input): Room
    {
        $input['name'] = trim((string) ($input['name'] ?? ''));

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:100', Rule::unique('rooms', 'name')->ignore($room?->id)],
            'notes' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ], ['name.unique' => __('There is already a room with this name.')])->validate();

        $room ??= new Room;
        $room->fill([
            'name' => $data['name'],
            'notes' => ($data['notes'] ?? '') !== '' ? $data['notes'] : null,
            'sort_order' => (int) ($data['sort_order'] ?? $room->sort_order ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? $room->is_active ?? true),
        ])->save();

        return $room;
    }
}
