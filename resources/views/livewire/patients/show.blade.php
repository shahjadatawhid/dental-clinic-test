<?php

use App\Actions\Patients\PossibleDuplicates;
use App\Models\Patient;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

/*
 * The patient profile: the hub every other module links to. Each tab is shown only
 * to users who may see that kind of record; modules fill their tab as they arrive.
 */
new class extends Component {
    public Patient $patient;

    #[Url]
    public string $tab = 'overview';

    public function mount(Patient $patient): void
    {
        Gate::authorize('patients.view');
        $this->patient = $patient;

        // A merged record lives on in the patient it was merged into.
        if ($patient->merged_into_id !== null) {
            session()->flash('status', __(':old was merged into this patient.', ['old' => $patient->patient_code]));
            $this->redirectRoute('patients.show', $patient->merged_into_id, navigate: true);
        }
    }

    /** @return array<string, array{label: string, module: string|null}> tabs this user may open */
    public function tabs(): array
    {
        $tabs = [
            'overview' => ['label' => __('Overview'), 'module' => null, 'can' => ['patients.view']],
            'appointments' => ['label' => __('Appointments'), 'module' => 'M04', 'can' => ['appointments.view', 'appointments.view_own']],
            'visits' => ['label' => __('Visits'), 'module' => 'M05', 'can' => ['visits.view']],
            'billing' => ['label' => __('Billing'), 'module' => 'M07', 'can' => ['invoices.view', 'invoices.view_own']],
        ];

        return collect($tabs)
            ->filter(fn (array $t) => Gate::any($t['can']))
            ->map(fn (array $t) => ['label' => $t['label'], 'module' => $t['module']])
            ->all();
    }

    public function with(): array
    {
        $tabs = $this->tabs();
        if (! array_key_exists($this->tab, $tabs)) {
            $this->tab = 'overview';
        }

        $phones = array_values(array_filter([$this->patient->phone, $this->patient->alt_phone]));

        return [
            'tabs' => $tabs,
            'family' => PossibleDuplicates::samePhone($phones, $this->patient->id),
            'canEdit' => Gate::allows('patients.update'),
        ];
    }

    public function rendering($view): void
    {
        $view->title($this->patient->full_name.' · '.$this->patient->patient_code);
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    {{-- Header: always shows who this is, so staff can confirm the right person --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('patients.index')" wire:navigate>{{ __('Patients') }}</flux:button>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <flux:heading size="xl" level="1">{{ $patient->summary() }}</flux:heading>
                @unless ($patient->is_active)
                    <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                @endunless
            </div>
            <flux:subheading class="mt-1 flex flex-wrap gap-x-3">
                <span class="font-mono font-medium text-zinc-800 dark:text-zinc-100">{{ $patient->patient_code }}</span>
                @if ($patient->legacy_code)
                    <span class="font-mono">{{ __('old ID') }} {{ $patient->legacy_code }}</span>
                @endif
                @if ($patient->phone)
                    <span>{{ $patient->phone }}</span>
                @endif
                @if ($patient->guardian_name)
                    <span>{{ __('Guardian') }}: {{ $patient->guardianLabel() }}</span>
                @endif
            </flux:subheading>
        </div>

        @if ($canEdit)
            <flux:button icon="pencil-square" :href="route('patients.edit', $patient)" wire:navigate>{{ __('Edit details') }}</flux:button>
        @endif
    </div>

    <flux:navbar class="-mb-2 border-b border-zinc-200 dark:border-zinc-700">
        @foreach ($tabs as $key => $t)
            <flux:navbar.item wire:click="$set('tab', '{{ $key }}')" :current="$tab === $key" class="cursor-pointer">{{ $t['label'] }}</flux:navbar.item>
        @endforeach
    </flux:navbar>

    @if ($tab === 'overview')
        <div class="grid gap-6 lg:grid-cols-3">
            <flux:card class="lg:col-span-2">
                <flux:heading size="lg" class="mb-4">{{ __('Details') }}</flux:heading>
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    @php
                        $rows = [
                            __('Date of birth') => $patient->date_of_birth?->format('d M Y') ?? ($patient->age_years !== null ? __('not known (age given)') : null),
                            __('Mobile') => $patient->phone,
                            __('Second mobile') => $patient->alt_phone,
                            __('Email') => $patient->email,
                            __('Address') => $patient->address,
                            __('Occupation') => $patient->occupation,
                            __('Preferred language') => $patient->preferred_language === 'en' ? __('English') : __('Bangla'),
                            __('Parent or guardian') => $patient->guardianLabel(),
                            __('Emergency contact') => trim(($patient->emergency_contact_name ?? '').' '.($patient->emergency_contact_phone ?? '')) ?: null,
                            __('Referred by') => $patient->referred_by,
                            __('Registered') => $patient->registered_on->format('d M Y').($patient->creator ? ' · '.$patient->creator->name : ''),
                        ];
                    @endphp
                    @foreach ($rows as $label => $value)
                        <div>
                            <dt class="text-zinc-500">{{ $label }}</dt>
                            <dd class="font-medium">{{ $value ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($patient->notes)
                    <flux:separator class="my-4" />
                    <flux:text class="text-xs uppercase tracking-wide">{{ __('Front-desk notes') }}</flux:text>
                    <p class="mt-1 whitespace-pre-line text-sm">{{ $patient->notes }}</p>
                @endif
            </flux:card>

            <div class="flex flex-col gap-6">
                <flux:card>
                    <flux:heading size="lg">{{ __('Others on this number') }}</flux:heading>
                    @forelse ($family as $member)
                        <a href="{{ route('patients.show', $member) }}" wire:navigate class="-mx-2 mt-2 block rounded-md px-2 py-1.5 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700">
                            <div class="font-medium">{{ $member->summary() }}</div>
                            <div class="text-xs text-zinc-500"><span class="font-mono">{{ $member->patient_code }}</span> · {{ $member->phone }}</div>
                        </a>
                    @empty
                        <flux:text class="mt-2 text-sm">{{ __('No other patients share this phone.') }}</flux:text>
                    @endforelse
                </flux:card>
            </div>
        </div>
    @else
        <flux:card class="py-10 text-center">
            <flux:text>{{ __(':tab will appear here once module :module is built.', ['tab' => $tabs[$tab]['label'], 'module' => $tabs[$tab]['module']]) }}</flux:text>
        </flux:card>
    @endif
</div>
