<?php

use App\Actions\Patients\SearchPatients;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

/*
 * Patient search box in the sidebar, on every page. Press "/" to jump to it.
 * Shows the first few matches; Enter opens the full search page.
 */
new class extends Component {
    public const LIMIT = 8;

    public const PERMISSIONS = ['patients.view', 'patients.view_minimal'];

    public string $search = '';

    public function openFullSearch(): void
    {
        abort_unless(Gate::any(self::PERMISSIONS), 403);

        $this->redirectRoute('patients.index', ['search' => trim($this->search)], navigate: true);
    }

    public function with(): array
    {
        $term = trim($this->search);
        $full = Gate::allows('patients.view');

        $results = $term === '' || ! Gate::any(self::PERMISSIONS)
            ? collect()
            : SearchPatients::query($term, byPhone: $full)->limit(self::LIMIT + 1)->get();

        return [
            'results' => $results->take(self::LIMIT),
            'more' => $results->count() > self::LIMIT,
            'full' => $full,
        ];
    }
}; ?>

<div x-data="{ open: false }" class="relative"
    x-on:keydown.window.slash="if (! ['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName) && ! $event.target.isContentEditable) { $event.preventDefault(); $refs.box.querySelector('input').focus() }"
    x-on:click.outside="open = false" x-on:keydown.escape="open = false">
    <form wire:submit="openFullSearch" x-ref="box">
        <flux:input wire:model.live.debounce.250ms="search" x-on:focus="open = true" x-on:input="open = true"
            icon="magnifying-glass" size="sm" kbd="/" :placeholder="__('Find patient')" :aria-label="__('Find patient')" />
    </form>

    @if (trim($search) !== '')
        <div x-show="open" x-cloak
            class="absolute inset-x-0 top-full z-50 mt-1 w-72 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
            @forelse ($results as $patient)
                <a href="{{ route('patients.index', ['search' => $patient->patient_code]) }}" wire:navigate
                    class="block px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700">
                    <div class="font-medium">{{ $patient->summary() }}</div>
                    <div class="text-xs text-zinc-500">
                        <span class="font-mono">{{ $patient->patient_code }}</span>
                        @if ($full && $patient->phone) · {{ $patient->phone }} @endif
                        @unless ($patient->is_active) · {{ __('Inactive') }} @endunless
                    </div>
                </a>
            @empty
                <div class="px-3 py-2 text-sm text-zinc-500">{{ __('No patients found.') }}</div>
            @endforelse

            @if ($more)
                <a href="{{ route('patients.index', ['search' => trim($search)]) }}" wire:navigate
                    class="block border-t border-zinc-200 px-3 py-2 text-xs font-medium text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-700">
                    {{ __('See all matches') }} →
                </a>
            @endif
        </div>
    @endif
</div>
