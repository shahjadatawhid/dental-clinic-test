<?php

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Support\Week;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Doctor schedules')] class extends Component {
    public function with(): array
    {
        $today = today();

        $doctors = Doctor::query()
            ->whereHas('staff', fn ($q) => $q->where('is_active', true))
            ->with([
                'staff:id,full_name',
                'schedules' => fn ($q) => $q->where(fn ($w) => $w->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))->with('blocks.chair'),
            ])
            ->get()
            ->sortBy(fn (Doctor $d) => $d->staff->full_name)
            ->map(function (Doctor $doctor) use ($today) {
                $current = $doctor->schedules->first(fn (DoctorSchedule $s) => $s->effective_from->lte($today));
                $upcoming = $doctor->schedules->filter(fn (DoctorSchedule $s) => $s->effective_from->gt($today))->sortBy('effective_from')->first();

                return [
                    'doctor' => $doctor,
                    'current' => $current,
                    'days' => $current?->blocks->groupBy('weekday') ?? collect(),
                    'upcoming' => $upcoming,
                    'awayToday' => \App\Models\DoctorTimeOff::where('doctor_id', $doctor->id)->overlapping($today, $today->copy()->addDay())->first(),
                ];
            });

        return [
            'holidayToday' => \App\Models\ClinicHoliday::forDate($today),
            'rows' => $doctors,
            'week' => Week::ORDER,
            'canManage' => Gate::allows('schedules.manage'),
        ];
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Doctor schedules') }}</flux:heading>
        <flux:subheading>{{ __('Weekly working hours in force today. Leave and holidays are handled separately.') }}</flux:subheading>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    @if ($holidayToday)
        <flux:callout icon="sun" color="amber" :heading="__('Clinic closed today: :name', ['name' => $holidayToday->name])" />
    @endif

    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900">
                <tr>
                    <th class="px-3 py-2">{{ __('Doctor') }}</th>
                    @foreach ($week as $wd)
                        <th class="px-3 py-2">{{ Week::short($wd) }}</th>
                    @endforeach
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($rows as $row)
                    <tr wire:key="doc-{{ $row['doctor']->id }}" class="align-top">
                        <td class="whitespace-nowrap px-3 py-2">
                            <div class="flex items-center gap-2 font-medium">
                                <span class="size-2.5 rounded-full" style="background: {{ $row['doctor']->calendar_color }}"></span>
                                {{ $row['doctor']->name }}
                            </div>
                            @if ($row['current'])
                                <div class="text-xs text-zinc-500">{{ __('since :date', ['date' => $row['current']->effective_from->format('d M Y')]) }}</div>
                            @endif
                            @if ($row['upcoming'])
                                <flux:badge size="sm" color="blue" class="mt-1">{{ __('Changes :date', ['date' => $row['upcoming']->effective_from->format('d M')]) }}</flux:badge>
                            @endif
                            @if ($row['awayToday'])
                                <flux:badge size="sm" color="red" class="mt-1">
                                    {{ $row['awayToday']->type === \App\Enums\TimeOffType::Leave ? __('On leave today') : __('Blocked :time', ['time' => $row['awayToday']->starts_at->format('H:i').'–'.$row['awayToday']->ends_at->format('H:i')]) }}
                                </flux:badge>
                            @endif
                            @unless ($row['doctor']->is_bookable)
                                <flux:badge size="sm" color="amber" class="mt-1">{{ __('Not bookable') }}</flux:badge>
                            @endunless
                        </td>
                        @foreach ($week as $wd)
                            <td class="whitespace-nowrap px-3 py-2">
                                @forelse ($row['days']->get($wd, []) as $block)
                                    <div>{{ $block->timeRange() }}</div>
                                    @if ($block->chair) <div class="text-xs text-zinc-500">{{ $block->chair->name }}</div> @endif
                                @empty
                                    <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                @endforelse
                            </td>
                        @endforeach
                        <td class="px-3 py-2 text-right">
                            <flux:button size="sm" variant="ghost" :icon="$canManage ? 'pencil-square' : 'eye'"
                                :href="route('schedules.edit', $row['doctor'])" wire:navigate>
                                {{ $canManage ? __('Edit') : __('View') }}
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-8 text-center text-zinc-500">{{ __('No active doctors. Add doctors under Staff first.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
