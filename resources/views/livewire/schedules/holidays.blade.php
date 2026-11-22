<?php

use App\Actions\Schedules\AddClinicHolidays;
use App\Models\ClinicHoliday;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('Clinic holidays')] class extends Component {
    #[Url]
    public int $year = 0;

    public string $name = '';

    public string $fromDate = '';

    public string $toDate = '';

    public function mount(): void
    {
        $this->year = $this->year ?: (int) now()->format('Y');
    }

    public function add(AddClinicHolidays $action): void
    {
        Gate::authorize('schedules.manage');
        $this->resetErrorBag();

        try {
            $added = $action->handle(['name' => $this->name, 'from_date' => $this->fromDate, 'to_date' => $this->toDate ?: null]);
        } catch (ValidationException $e) {
            $map = ['name' => 'name', 'from_date' => 'fromDate', 'to_date' => 'toDate'];
            foreach ($e->errors() as $field => $messages) {
                $this->addError($map[$field] ?? $field, $messages[0]);
            }

            return;
        }

        $this->year = (int) substr($this->fromDate, 0, 4);
        session()->flash('status', trans_choice(':count day added.|:count days added.', $added, ['count' => $added]));
        $this->reset('name', 'fromDate', 'toDate');
    }

    public function remove(int $id): void
    {
        Gate::authorize('schedules.manage');

        ClinicHoliday::findOrFail($id)->delete();
    }

    public function with(): array
    {
        return [
            'holidays' => ClinicHoliday::whereYear('date', $this->year)->orderBy('date')->get(),
            'canManage' => Gate::allows('schedules.manage'),
        ];
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-6 p-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Clinic holidays') }}</flux:heading>
        <flux:subheading>{{ __('Days the clinic is closed. No appointments can be booked with any doctor.') }}</flux:subheading>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    @if ($canManage)
        <form wire:submit="add" class="flex flex-col gap-3 rounded-lg border border-dashed border-zinc-300 p-4 dark:border-zinc-600">
            <div class="flex flex-wrap items-start gap-3">
                <flux:input wire:model="name" :label="__('Holiday')" :placeholder="__('e.g. Eid-ul-Fitr')" class="min-w-48 flex-1" />
                <flux:input type="date" wire:model="fromDate" :label="__('First day')" class="w-44" />
                <flux:input type="date" wire:model="toDate" :label="__('Last day')" :description="__('Blank = one day')" class="w-44" />
            </div>
            <flux:error name="name" />
            <flux:error name="fromDate" />
            <flux:error name="toDate" />
            <div><flux:button type="submit" size="sm" icon="plus">{{ __('Add holiday') }}</flux:button></div>
        </form>
    @endif

    <div class="flex items-center gap-2">
        <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="$set('year', {{ $year - 1 }})" :aria-label="__('Previous year')" />
        <flux:heading size="lg">{{ $year }}</flux:heading>
        <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="$set('year', {{ $year + 1 }})" :aria-label="__('Next year')" />
    </div>

    <ul class="divide-y divide-zinc-100 rounded-lg border border-zinc-200 text-sm dark:divide-zinc-800 dark:border-zinc-700">
        @forelse ($holidays as $holiday)
            <li wire:key="h-{{ $holiday->id }}" @class(['flex items-center justify-between gap-2 px-3 py-2', 'text-zinc-400' => $holiday->date->isPast() && ! $holiday->date->isToday()])>
                <span><span class="inline-block w-32 font-medium">{{ $holiday->date->format('D j M') }}</span> {{ $holiday->name }}</span>
                @if ($canManage)
                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="remove({{ $holiday->id }})"
                        wire:confirm="{{ __('Remove :name on :date?', ['name' => $holiday->name, 'date' => $holiday->date->format('j M')]) }}">{{ __('Remove') }}</flux:button>
                @endif
            </li>
        @empty
            <li class="px-3 py-2 text-zinc-500">{{ __('No holidays entered for :year.', ['year' => $year]) }}</li>
        @endforelse
    </ul>
</div>
