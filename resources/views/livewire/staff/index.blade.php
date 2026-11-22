<?php

use App\Enums\StaffType;
use App\Models\Staff;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Staff')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $status = 'active';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $staff = Staff::query()
            ->with(['doctor', 'user:id,email,is_active'])
            ->when(trim($this->search) !== '', function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(fn ($q) => $q->where('full_name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('designation', 'like', $term)
                    ->orWhereHas('doctor', fn ($d) => $d->where('specialty', 'like', $term)->orWhere('registration_no', 'like', $term)));
            })
            ->when(StaffType::tryFrom($this->type), fn ($query, StaffType $type) => $query->where('type', $type))
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByRaw("type = 'doctor' desc")
            ->orderBy('full_name')
            ->paginate(25);

        return [
            'staff' => $staff,
            'types' => StaffType::cases(),
            'canManage' => Gate::allows('staff.manage'),
        ];
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Staff') }}</flux:heading>
            <flux:subheading>{{ __('Doctors and other staff. People who leave are deactivated, never deleted.') }}</flux:subheading>
        </div>

        @if ($canManage)
            <flux:button variant="primary" icon="plus" :href="route('staff.create')" wire:navigate>{{ __('Add staff member') }}</flux:button>
        @endif
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, phone, specialty, BMDC no.')" />

        <flux:select wire:model.live="type">
            <flux:select.option value="">{{ __('Doctors and staff') }}</flux:select.option>
            @foreach ($types as $t)
                <flux:select.option :value="$t->value">{{ $t->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="status">
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$staff">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Role at clinic') }}</flux:table.column>
            <flux:table.column>{{ __('Phone') }}</flux:table.column>
            <flux:table.column>{{ __('Login') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            @if ($canManage)
                <flux:table.column />
            @endif
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($staff as $person)
                <flux:table.row :key="$person->id">
                    <flux:table.cell variant="strong">
                        <div class="flex items-center gap-2">
                            @if ($person->doctor)
                                <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $person->doctor->calendar_color }}"></span>
                            @endif
                            {{ $person->full_name }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div>{{ $person->doctor?->specialty ?? $person->designation ?? $person->type->label() }}</div>
                        @if ($person->doctor)
                            <div class="text-xs text-zinc-500">
                                {{ $person->doctor->qualifications }}
                                @if ($person->doctor->registration_no) · BMDC {{ $person->doctor->registration_no }} @endif
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $person->phone ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">
                        @if ($person->user)
                            {{ $person->user->email }}
                            @unless ($person->user->is_active) <flux:badge size="sm" color="zinc">{{ __('login off') }}</flux:badge> @endunless
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if (! $person->is_active)
                            <flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>
                        @elseif ($person->doctor && ! $person->doctor->is_bookable)
                            <flux:badge size="sm" color="amber">{{ __('Not bookable') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    @if ($canManage)
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('staff.edit', $person)" wire:navigate>{{ __('Edit') }}</flux:button>
                        </flux:table.cell>
                    @endif
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">{{ __('No staff found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
