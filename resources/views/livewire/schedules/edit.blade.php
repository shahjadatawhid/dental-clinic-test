<?php

use App\Actions\Schedules\RemoveUpcomingSchedule;
use App\Actions\Schedules\SaveDoctorSchedule;
use App\Models\Chair;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Support\Week;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public Doctor $doctor;

    public string $effectiveFrom = '';

    public string $note = '';

    /** @var array<int, list<array{start_time: string, end_time: string, chair_id: string}>> weekday => blocks */
    public array $days = [];

    public function mount(Doctor $doctor): void
    {
        $this->doctor = $doctor->load('staff');
        $latest = $doctor->schedules()->with('blocks')->first();

        foreach (Week::ORDER as $wd) {
            $this->days[$wd] = [];
        }

        if ($latest) {
            foreach ($latest->blocks as $block) {
                $this->days[$block->weekday][] = [
                    'start_time' => substr($block->start_time, 0, 5),
                    'end_time' => substr($block->end_time, 0, 5),
                    'chair_id' => (string) ($block->chair_id ?? ''),
                ];
            }
            $this->note = (string) $latest->note;
        }

        // Editing an upcoming routine keeps its date; otherwise changes start today.
        $this->effectiveFrom = ($latest && ! $latest->hasStarted() ? $latest->effective_from : today())->toDateString();
    }

    public function addBlock(int $weekday): void
    {
        $last = end($this->days[$weekday]) ?: null;
        $this->days[$weekday][] = $last
            ? ['start_time' => $last['end_time'], 'end_time' => '', 'chair_id' => $last['chair_id']]
            : ['start_time' => '17:00', 'end_time' => '21:00', 'chair_id' => ''];
    }

    public function removeBlock(int $weekday, int $index): void
    {
        unset($this->days[$weekday][$index]);
        $this->days[$weekday] = array_values($this->days[$weekday]);
    }

    /** Copy one day's hours to every other day that already has hours or is a working day (not Friday). */
    public function copyToWorkingDays(int $fromWeekday): void
    {
        foreach (Week::ORDER as $wd) {
            if ($wd !== $fromWeekday && $wd !== \Carbon\CarbonInterface::FRIDAY) {
                $this->days[$wd] = $this->days[$fromWeekday];
            }
        }
    }

    public function save(SaveDoctorSchedule $action): void
    {
        Gate::authorize('schedules.manage');
        $this->resetErrorBag();

        $blocks = [];
        $where = []; // flat index → "days.{weekday}.{position}" for error messages
        foreach ($this->days as $wd => $dayBlocks) {
            foreach ($dayBlocks as $pos => $block) {
                $where[count($blocks)] = "days.{$wd}.{$pos}";
                $blocks[] = ['weekday' => $wd, ...$block];
            }
        }

        try {
            $action->handle($this->doctor, $this->effectiveFrom, $blocks, $this->note ?: null);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                if (preg_match('/^blocks\.(\d+)\.(\w+)$/', $key, $m)) {
                    $this->addError(($where[(int) $m[1]] ?? 'days').'.'.$m[2], $messages[0]);
                } else {
                    $this->addError($key === 'effective_from' ? 'effectiveFrom' : $key, $messages[0]);
                }
            }

            return;
        }

        session()->flash('status', __('Hours saved for :name from :date.', [
            'name' => $this->doctor->name,
            'date' => \Illuminate\Support\Carbon::parse($this->effectiveFrom)->format('d M Y'),
        ]));
        $this->redirectRoute('schedules.index', navigate: true);
    }

    public function removeUpcoming(int $scheduleId, RemoveUpcomingSchedule $action): void
    {
        Gate::authorize('schedules.manage');

        $schedule = DoctorSchedule::where('doctor_id', $this->doctor->id)->findOrFail($scheduleId);

        try {
            $action->handle($schedule);
        } catch (ValidationException $e) {
            $this->addError('history', $e->errors()['schedule'][0]);

            return;
        }

        $this->mount($this->doctor->fresh());
    }

    public function with(): array
    {
        return [
            'week' => Week::ORDER,
            'chairs' => Chair::usable()->with('room')->get()->sortBy(fn (Chair $c) => [$c->room->sort_order, $c->room->name, $c->sort_order]),
            'history' => $this->doctor->schedules()->withCount('blocks')->get(),
            'canManage' => Gate::allows('schedules.manage'),
            'hoursPerWeek' => collect($this->days)->flatten(1)->sum(function ($b) {
                if (! preg_match('/^\d{2}:\d{2}$/', $b['start_time'] ?? '') || ! preg_match('/^\d{2}:\d{2}$/', $b['end_time'] ?? '')) {
                    return 0;
                }
                [$sh, $sm] = explode(':', $b['start_time']);
                [$eh, $em] = explode(':', $b['end_time']);

                return max(0, ($eh * 60 + $em) - ($sh * 60 + $sm)) / 60;
            }),
        ];
    }

    public function rendering($view): void
    {
        $view->title(__('Hours: :name', ['name' => $this->doctor->name]));
    }
}; ?>

<div class="flex max-w-4xl flex-col gap-6 p-6">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('schedules.index')" wire:navigate>{{ __('Doctor schedules') }}</flux:button>
        <flux:heading size="xl" level="1" class="mt-2">{{ $doctor->name }}</flux:heading>
        <flux:subheading>{{ __('Weekly working hours. Saving creates a routine from the chosen date; earlier dates keep their old routine.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <fieldset @disabled(! $canManage) class="flex flex-col gap-6">
            <div class="grid gap-4 sm:grid-cols-[12rem_1fr]">
                <flux:input type="date" wire:model="effectiveFrom" :label="__('Starts from')" />
                <flux:input wire:model="note" :label="__('Note (optional)')" :placeholder="__('e.g. Ramadan hours')" />
            </div>
            <flux:error name="effectiveFrom" />

            <flux:card class="flex flex-col divide-y divide-zinc-100 p-0 dark:divide-zinc-800">
                @foreach ($week as $wd)
                    <div wire:key="day-{{ $wd }}" class="grid gap-3 p-4 sm:grid-cols-[7rem_1fr]">
                        <div class="font-medium">{{ Week::name($wd) }}</div>
                        <div class="flex flex-col gap-2">
                            @forelse ($days[$wd] as $i => $block)
                                <div wire:key="day-{{ $wd }}-{{ $i }}" class="flex flex-wrap items-start gap-2">
                                    <flux:input type="time" size="sm" wire:model.live.debounce.500ms="days.{{ $wd }}.{{ $i }}.start_time" class="w-32" :aria-label="__('Start')" />
                                    <span class="pt-1.5 text-zinc-400">–</span>
                                    <flux:input type="time" size="sm" wire:model.live.debounce.500ms="days.{{ $wd }}.{{ $i }}.end_time" class="w-32" :aria-label="__('End')" />
                                    <flux:select size="sm" wire:model="days.{{ $wd }}.{{ $i }}.chair_id" class="w-48" :aria-label="__('Chair')">
                                        <flux:select.option value="">{{ __('Any chair') }}</flux:select.option>
                                        @foreach ($chairs as $chair)
                                            <flux:select.option :value="$chair->id">{{ $chair->label }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    @if ($canManage)
                                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeBlock({{ $wd }}, {{ $i }})" :aria-label="__('Remove')" />
                                    @endif
                                    @foreach (['start_time', 'end_time', 'chair_id'] as $f)
                                        @error("days.$wd.$i.$f") <div class="w-full text-sm text-red-600">{{ $message }}</div> @enderror
                                    @endforeach
                                </div>
                            @empty
                                <span class="text-sm text-zinc-400">{{ __('Off') }}</span>
                            @endforelse

                            @if ($canManage)
                                <div class="flex gap-1">
                                    <flux:button size="xs" variant="ghost" icon="plus" wire:click="addBlock({{ $wd }})">{{ __('Add time') }}</flux:button>
                                    @if ($days[$wd])
                                        <flux:button size="xs" variant="ghost" icon="document-duplicate" wire:click="copyToWorkingDays({{ $wd }})"
                                            wire:confirm="{{ __('Copy these hours to every day except Friday?') }}">{{ __('Copy to Sat–Thu') }}</flux:button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </flux:card>
        </fieldset>

        <div class="flex flex-wrap items-center gap-4">
            @if ($canManage)
                <flux:button type="submit" variant="primary">{{ __('Save hours') }}</flux:button>
            @endif
            <flux:text>{{ __(':hours hours per week', ['hours' => rtrim(rtrim(number_format($hoursPerWeek, 1), '0'), '.')]) }}</flux:text>
        </div>
    </form>

    <div class="flex flex-col gap-3">
        <flux:heading size="lg">{{ __('Routine history') }}</flux:heading>
        @error('history') <flux:callout variant="danger" icon="x-circle" :heading="$message" /> @enderror

        <ul class="divide-y divide-zinc-100 rounded-lg border border-zinc-200 text-sm dark:divide-zinc-800 dark:border-zinc-700">
            @forelse ($history as $version)
                <li wire:key="v-{{ $version->id }}" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                    <span>
                        {{ $version->effective_from->format('d M Y') }} → {{ $version->effective_to?->format('d M Y') ?? __('until changed') }}
                        <span class="text-zinc-500">· {{ trans_choice(':count block|:count blocks', $version->blocks_count, ['count' => $version->blocks_count]) }}</span>
                        @if ($version->note) <span class="text-zinc-500">· {{ $version->note }}</span> @endif
                    </span>
                    <span class="flex items-center gap-2">
                        @if (! $version->hasStarted())
                            <flux:badge size="sm" color="blue">{{ __('Upcoming') }}</flux:badge>
                            @if ($canManage)
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeUpcoming({{ $version->id }})"
                                    wire:confirm="{{ __('Remove this upcoming routine? The previous routine will continue.') }}">{{ __('Remove') }}</flux:button>
                            @endif
                        @elseif ($version->effective_to === null || $version->effective_to->gte(today()))
                            <flux:badge size="sm" color="green">{{ __('Current') }}</flux:badge>
                        @endif
                    </span>
                </li>
            @empty
                <li class="px-3 py-2 text-zinc-500">{{ __('No hours set yet.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
