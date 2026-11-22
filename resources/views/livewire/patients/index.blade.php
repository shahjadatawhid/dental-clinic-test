<?php

use App\Actions\Patients\SearchPatients;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Patients')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'active';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        // Lab coordinators etc. (patients.view_minimal) may only identify patients by name/code.
        $full = Gate::allows('patients.view');
        $status = in_array($this->status, ['active', 'inactive', 'all'], true) ? $this->status : 'active';

        return [
            'patients' => SearchPatients::query($this->search, byPhone: $full, status: $status)
                ->with('mergedInto:id,patient_code')
                ->paginate(25),
            'full' => $full,
            'canCreate' => Gate::allows('patients.create'),
            'canUpdate' => Gate::allows('patients.update'),
        ];
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <flux:heading size="xl" level="1">{{ __('Patients') }}</flux:heading>
        <flux:subheading>
            {{ $full
                ? __('Search by name, phone, patient code or old ID. One phone can belong to a whole family — check the code and age before choosing.')
                : __('Search by name, patient code or old ID.') }}
        </flux:subheading>
    </div>

        @if ($canCreate)
            <flux:button variant="primary" icon="plus" :href="route('patients.create')" wire:navigate>{{ __('Register patient') }}</flux:button>
        @endif
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" autofocus
                :placeholder="$full ? __('Name, phone, FD-11-26-000001 or old ID') : __('Name, FD-11-26-000001 or old ID')" />
        </div>

        <flux:select wire:model.live="status">
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            <flux:select.option value="all">{{ __('All, including merged') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$patients">
        <flux:table.columns>
            <flux:table.column>{{ __('Code') }}</flux:table.column>
            <flux:table.column>{{ __('Patient') }}</flux:table.column>
            @if ($full)
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Registered') }}</flux:table.column>
            @endif
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            @if ($canUpdate)
                <flux:table.column />
            @endif
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($patients as $patient)
                <flux:table.row :key="$patient->id">
                    <flux:table.cell>
                        <div class="font-mono text-sm">{{ $patient->patient_code }}</div>
                        @if ($patient->legacy_code)
                            <div class="font-mono text-xs text-zinc-500">{{ __('old') }}: {{ $patient->legacy_code }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell variant="strong">
                        @if ($full && ! $patient->merged_into_id)
                            <flux:link :href="route('patients.show', $patient)" wire:navigate variant="ghost">{{ $patient->summary() }}</flux:link>
                        @else
                            {{ $patient->summary() }}
                        @endif
                    </flux:table.cell>
                    @if ($full)
                        <flux:table.cell>
                            <div>{{ $patient->phone ?? '—' }}</div>
                            @if ($patient->alt_phone)
                                <div class="text-xs text-zinc-500">{{ $patient->alt_phone }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $patient->registered_on->format('d M Y') }}</flux:table.cell>
                    @endif
                    <flux:table.cell>
                        @if ($patient->merged_into_id)
                            <flux:badge size="sm" color="amber">{{ __('Merged into :code', ['code' => $patient->mergedInto?->patient_code]) }}</flux:badge>
                        @elseif (! $patient->is_active)
                            <flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    @if ($canUpdate)
                        <flux:table.cell align="end">
                            @unless ($patient->merged_into_id)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('patients.edit', $patient)" wire:navigate>{{ __('Edit') }}</flux:button>
                            @endunless
                        </flux:table.cell>
                    @endif
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">
                        {{ __('No patients found.') }}
                        @if ($canCreate && trim($search) !== '')
                            <flux:link :href="route('patients.create', ['name' => trim($search)])" wire:navigate>{{ __('Register “:name” as a new patient', ['name' => trim($search)]) }}</flux:link>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
