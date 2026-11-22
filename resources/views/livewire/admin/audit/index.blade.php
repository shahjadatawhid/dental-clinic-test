<?php

use App\Authorization\AuditLogAccess;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

new #[Title('Audit history')] class extends Component {
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $log = '';

    #[Url]
    public string $event = '';

    #[Url]
    public string $user = '';

    #[Url]
    public string $search = '';

    /** Set by links such as "History" on the user list. */
    #[Url]
    public string $subjectType = '';

    #[Url]
    public string $subjectId = '';

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('from', 'to', 'log', 'event', 'user', 'search', 'subjectType', 'subjectId');
        $this->resetPage();
    }

    public function with(): array
    {
        $viewer = auth()->user();
        $base = AuditLogAccess::scope(Activity::query(), $viewer);

        $entries = (clone $base)
            ->with(['causer', 'subject'])
            ->when($this->from !== '', fn ($q) => $q->where('created_at', '>=', Carbon::parse($this->from)->startOfDay()))
            ->when($this->to !== '', fn ($q) => $q->where('created_at', '<=', Carbon::parse($this->to)->endOfDay()))
            ->when($this->log !== '', fn ($q) => $q->where('log_name', $this->log))
            ->when($this->event !== '', fn ($q) => $q->where('event', $this->event))
            ->when($this->user === 'system', fn ($q) => $q->whereNull('causer_id'))
            ->when(ctype_digit($this->user), fn ($q) => $q->where('causer_type', (new User)->getMorphClass())->where('causer_id', $this->user))
            ->when($this->subjectType !== '' && $this->subjectId !== '', fn ($q) => $q
                ->where('subject_type', $this->subjectType)->where('subject_id', $this->subjectId))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($w) => $w->where('description', 'like', $term)
                    ->orWhere('properties', 'like', $term)
                    ->orWhere('attribute_changes', 'like', $term));
            })
            ->latest('id')
            ->paginate(50);

        return [
            'entries' => $entries,
            'logs' => (clone $base)->whereNotNull('log_name')->distinct()->orderBy('log_name')->pluck('log_name'),
            'events' => (clone $base)->whereNotNull('event')->distinct()->orderBy('event')->pluck('event'),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'subjectFilter' => $this->subjectType !== '' ? class_basename($this->subjectType).' #'.$this->subjectId : null,
        ];
    }

    /** Human label for whatever the entry is about. */
    public function subjectLabel(Activity $entry): ?string
    {
        if ($entry->subject_type === null) {
            return null;
        }

        $name = $entry->subject?->name ?? $entry->subject?->key ?? $entry->subject?->series?->value ?? null;
        $type = Str::headline(class_basename($entry->subject_type));

        return $name ? "{$type}: {$name}" : "{$type} #{$entry->subject_id}";
    }

    /** @return array<string, array{old: mixed, new: mixed}> */
    public function changes(Activity $entry): array
    {
        $new = (array) ($entry->attribute_changes['attributes'] ?? []);
        $old = (array) ($entry->attribute_changes['old'] ?? []);
        $rows = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            $rows[$field] = ['old' => $old[$field] ?? null, 'new' => $new[$field] ?? null];
        }

        return $rows;
    }

    public function show(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => $value === [] ? '—' : implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $value)),
            default => (string) $value,
        };
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Audit history') }}</flux:heading>
        <flux:subheading>{{ __('Who changed what, and when. Entries cannot be edited or deleted.') }}</flux:subheading>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input type="date" wire:model.live="from" :label="__('From')" />
        <flux:input type="date" wire:model.live="to" :label="__('To')" />

        <flux:select wire:model.live="user" :label="__('Done by')">
            <flux:select.option value="">{{ __('Anyone') }}</flux:select.option>
            <flux:select.option value="system">{{ __('System / not signed in') }}</flux:select.option>
            @foreach ($users as $u)
                <flux:select.option :value="$u->id">{{ $u->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Text, reason, value...')" />

        <flux:select wire:model.live="log" :label="__('Area')">
            <flux:select.option value="">{{ __('All areas') }}</flux:select.option>
            @foreach ($logs as $l)
                <flux:select.option :value="$l">{{ Str::headline($l) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="event" :label="__('Action')">
            <flux:select.option value="">{{ __('All actions') }}</flux:select.option>
            @foreach ($events as $e)
                <flux:select.option :value="$e">{{ Str::headline($e) }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-end gap-2 lg:col-span-2">
            @if ($subjectFilter)
                <flux:badge color="blue">{{ __('Only') }}: {{ $subjectFilter }}</flux:badge>
            @endif
            <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900">
                <tr>
                    <th class="px-3 py-2">{{ __('When') }}</th>
                    <th class="px-3 py-2">{{ __('Who') }}</th>
                    <th class="px-3 py-2">{{ __('Action') }}</th>
                    <th class="px-3 py-2">{{ __('What') }}</th>
                    <th class="px-3 py-2">{{ __('Details') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($entries as $entry)
                    @php($changes = $this->changes($entry))
                    @php($props = collect($entry->properties)->except(['ip', 'user_agent'])->all())
                    <tr wire:key="audit-{{ $entry->id }}" class="align-top">
                        <td class="whitespace-nowrap px-3 py-2 text-zinc-500">
                            {{ $entry->created_at->format('d M Y') }}<br>
                            <span class="text-xs">{{ $entry->created_at->format('h:i:s A') }}</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2">
                            {{ $entry->causer?->name ?? __('System') }}
                            @if ($entry->properties['ip'] ?? null)
                                <div class="text-xs text-zinc-500">{{ $entry->properties['ip'] }}</div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-2">
                            <flux:badge size="sm" :color="match ($entry->event) {
                                'created' => 'green', 'deleted', 'voided', 'deactivated', 'login_failed', 'login_blocked' => 'red',
                                'updated', 'permissions_changed', 'roles_changed' => 'amber', default => 'zinc' }">
                                {{ Str::headline($entry->event ?? 'event') }}
                            </flux:badge>
                            <div class="mt-1 text-xs text-zinc-500">{{ Str::headline($entry->log_name ?? '') }}</div>
                        </td>
                        <td class="px-3 py-2">
                            <div class="font-medium">{{ $entry->description }}</div>
                            @if ($label = $this->subjectLabel($entry))
                                <div class="text-xs text-zinc-500">{{ $label }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($changes || $props)
                                <details>
                                    <summary class="cursor-pointer text-zinc-600 dark:text-zinc-300">
                                        {{ $changes ? trans_choice(':count field|:count fields', count($changes), ['count' => count($changes)]) : __('Details') }}
                                        @if (isset($props['reason']))
                                            · <span class="italic">“{{ Str::limit($props['reason'], 60) }}”</span>
                                        @endif
                                    </summary>
                                    <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                                        @foreach ($changes as $field => $change)
                                            <dt class="font-mono text-zinc-500">{{ $field }}</dt>
                                            <dd>
                                                @if ($entry->event !== 'created')
                                                    <span class="text-red-600 line-through">{{ $this->show($change['old']) }}</span> →
                                                @endif
                                                <span class="text-green-700 dark:text-green-400">{{ $this->show($change['new']) }}</span>
                                            </dd>
                                        @endforeach
                                        @foreach ($props as $key => $value)
                                            <dt class="font-mono text-zinc-500">{{ $key }}</dt>
                                            <dd>{{ $this->show($value) }}</dd>
                                        @endforeach
                                    </dl>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-8 text-center text-zinc-500">{{ __('No audit entries match these filters.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $entries->links() }}
</div>
