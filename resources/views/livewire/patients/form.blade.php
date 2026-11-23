<?php

use App\Actions\Patients\PossibleDuplicates;
use App\Actions\Patients\RegisterPatient;
use App\Actions\Patients\UpdatePatient;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public ?Patient $patient = null;

    /** @var array<string, mixed> */
    public array $form = [
        'full_name' => '',
        'gender' => '',
        'date_of_birth' => '',
        'age_years' => '',
        'phone' => '',
        'alt_phone' => '',
        'email' => '',
        'address' => '',
        'occupation' => '',
        'preferred_language' => 'bn',
        'guardian_name' => '',
        'guardian_relation' => '',
        'emergency_contact_name' => '',
        'emergency_contact_phone' => '',
        'referred_by' => '',
        'legacy_code' => '',
        'notes' => '',
        'is_active' => true,
    ];

    /** Reception ticked "this is a different person" after a strong duplicate warning. */
    public bool $confirmedDifferent = false;

    public function mount(?Patient $patient = null): void
    {
        if ($patient?->exists) {
            Gate::authorize('patients.update');
            abort_if($patient->merged_into_id !== null, 404);

            $this->patient = $patient;
            foreach (array_keys($this->form) as $field) {
                $this->form[$field] = $patient->{$field} ?? '';
            }
            $this->form['date_of_birth'] = $patient->date_of_birth?->toDateString() ?? '';
            // Show today's age; UpdatePatient keeps the original pair if it isn't changed.
            $this->form['age_years'] = $patient->date_of_birth ? '' : (string) ($patient->age() ?? '');
            $this->form['is_active'] = $patient->is_active;
        } else {
            Gate::authorize('patients.create');
            // Coming from a search with no result: start with what was typed.
            $typed = trim((string) request()->query('name', ''));
            $this->form[preg_match('/^[\d\s()+-]+$/', $typed) ? 'phone' : 'full_name'] = $typed;
        }
    }

    public function updatedForm(mixed $value, string $key): void
    {
        // Changing what duplicates are matched on needs a new confirmation.
        if (in_array($key, ['full_name', 'phone', 'alt_phone', 'date_of_birth', 'age_years'], true)) {
            $this->confirmedDifferent = false;
        }
    }

    public function save(RegisterPatient $register, UpdatePatient $update): void
    {
        Gate::authorize($this->patient ? 'patients.update' : 'patients.create');
        $this->resetErrorBag();

        if (! $this->patient && ! $this->confirmedDifferent && $this->samePerson()->isNotEmpty()) {
            $this->addError('confirmedDifferent', __('Open the existing record, or tick the box to confirm this is a different person.'));

            return;
        }

        $input = $this->form;
        if ($this->patient === null) {
            unset($input['is_active']);
        }

        try {
            $saved = $this->patient ? $update->handle($this->patient, $input) : $register->handle($input);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError("form.{$field}", $messages[0]);
            }

            return;
        }

        session()->flash('status', $this->patient
            ? __(':name (:code) has been updated.', ['name' => $saved->full_name, 'code' => $saved->patient_code])
            : __(':name has been registered as :code.', ['name' => $saved->full_name, 'code' => $saved->patient_code]));

        $this->redirectRoute('patients.show', $saved, navigate: true);
    }

    /** @return \Illuminate\Support\Collection<int, array{patient: Patient, match: string}> */
    private function matches(): \Illuminate\Support\Collection
    {
        if (mb_strlen(trim((string) $this->form['full_name'])) < 2 && trim((string) $this->form['phone']) === '') {
            return collect();
        }

        return PossibleDuplicates::check($this->form, $this->patient?->id);
    }

    /** @return \Illuminate\Support\Collection<int, array{patient: Patient, match: string}> */
    private function samePerson(): \Illuminate\Support\Collection
    {
        return $this->matches()->where('match', PossibleDuplicates::SAME_PERSON)->values();
    }

    public function with(): array
    {
        $age = null;
        if ($this->form['date_of_birth'] !== '' && strtotime((string) $this->form['date_of_birth'])) {
            $age = Carbon::parse($this->form['date_of_birth'])->age;
        } elseif (is_numeric($this->form['age_years'])) {
            $age = (int) $this->form['age_years'];
        }

        return [
            'matches' => $this->matches(),
            'isChild' => $age !== null && $age < Patient::ADULT_AGE,
            'relations' => Patient::GUARDIAN_RELATIONS,
        ];
    }

    public function rendering($view): void
    {
        $view->title($this->patient ? __('Edit :name', ['name' => $this->patient->full_name]) : __('Register patient'));
    }
}; ?>

<div class="flex max-w-5xl flex-col gap-6 p-6">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('patients.index')" wire:navigate>{{ __('Patients') }}</flux:button>
        <flux:heading size="xl" level="1" class="mt-2">
            {{ $patient ? $patient->full_name : __('Register patient') }}
        </flux:heading>
        @if ($patient)
            <flux:subheading>
                <span class="font-mono">{{ $patient->patient_code }}</span> · {{ __('registered :date', ['date' => $patient->registered_on->format('d M Y')]) }}
            </flux:subheading>
        @else
            <flux:subheading>{{ __('The patient code is given automatically when you save. Each person gets their own record, even when a family shares one phone.') }}</flux:subheading>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="flex flex-col gap-6 lg:col-span-2">
            <flux:card class="flex flex-col gap-5">
                <flux:heading size="lg">{{ __('Patient') }}</flux:heading>

                <flux:input wire:model.live.debounce.500ms="form.full_name" :label="__('Full name')" required autofocus />

                <div class="grid gap-5 sm:grid-cols-3">
                    <flux:select wire:model="form.gender" :label="__('Gender')">
                        <flux:select.option value="">—</flux:select.option>
                        <flux:select.option value="female">{{ __('Female') }}</flux:select.option>
                        <flux:select.option value="male">{{ __('Male') }}</flux:select.option>
                        <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
                    </flux:select>
                    <flux:input wire:model.live.blur="form.date_of_birth" type="date" :max="today()->toDateString()" :label="__('Date of birth')" />
                    <flux:input wire:model.live.debounce.500ms="form.age_years" type="number" min="0" max="120" :label="__('Age (years)')"
                        :disabled="$form['date_of_birth'] !== ''" :description="__('If the birthday is not known.')" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model.live.blur="form.phone" :label="__('Mobile')" placeholder="01XXXXXXXXX"
                        :description="__('A family can share one number.')" />
                    <flux:input wire:model.live.blur="form.alt_phone" :label="__('Second mobile')" placeholder="01XXXXXXXXX" />
                    <flux:input wire:model="form.email" type="email" :label="__('Email')" />
                    <flux:input wire:model="form.occupation" :label="__('Occupation')" />
                </div>

                <flux:textarea wire:model="form.address" rows="2" :label="__('Address')" />

                <flux:radio.group wire:model="form.preferred_language" :label="__('Preferred language for SMS and printouts')" variant="segmented">
                    <flux:radio value="bn" :label="__('Bangla')" />
                    <flux:radio value="en" :label="__('English')" />
                </flux:radio.group>
            </flux:card>

            <flux:card class="flex flex-col gap-5">
                <div>
                    <flux:heading size="lg">{{ __('Parent or guardian') }}</flux:heading>
                    <flux:text class="text-sm">
                        {{ $isChild ? __('This patient is a child — please record who brings them.') : __('For children, or anyone who comes with a carer.') }}
                    </flux:text>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="form.guardian_name" :label="__('Name')" />
                    <flux:select wire:model="form.guardian_relation" :label="__('Relation to patient')">
                        <flux:select.option value="">—</flux:select.option>
                        @foreach ($relations as $relation)
                            <flux:select.option :value="$relation">{{ __(ucfirst($relation)) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-5">
                <flux:heading size="lg">{{ __('Other details') }}</flux:heading>
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="form.emergency_contact_name" :label="__('Emergency contact')" />
                    <flux:input wire:model="form.emergency_contact_phone" :label="__('Emergency contact mobile')" placeholder="01XXXXXXXXX" />
                    <flux:input wire:model="form.referred_by" :label="__('Referred by')" />
                    <flux:input wire:model="form.legacy_code" :label="__('Old patient ID')" placeholder="FD-01-23-0208"
                        :description="__('From the old register or spreadsheet, if any.')" />
                </div>
                <flux:textarea wire:model="form.notes" rows="2" :label="__('Front-desk notes')"
                    :description="__('Not for medical information — that goes in the medical history.')" />

                @if ($patient)
                    <flux:checkbox wire:model="form.is_active" :label="__('Active patient')"
                        :description="__('Untick instead of deleting. Inactive patients stay searchable and keep all their history.')" />
                @endif
            </flux:card>

            @if (! $patient && $matches->contains('match', \App\Actions\Patients\PossibleDuplicates::SAME_PERSON))
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>{{ __('This person may already be registered') }}</flux:callout.heading>
                    <flux:callout.text>
                        <flux:checkbox wire:model="confirmedDifferent" :label="__('I checked — this is a different person (e.g. a namesake in the same family)')" />
                    </flux:callout.text>
                </flux:callout>
                <flux:error name="confirmedDifferent" />
            @endif

            <div class="flex gap-3">
                <flux:button type="submit" variant="primary">{{ $patient ? __('Save changes') : __('Register patient') }}</flux:button>
                <flux:button variant="ghost" :href="$patient ? route('patients.show', $patient) : route('patients.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>

        {{-- Possible duplicates, updated as reception types --}}
        <aside class="flex flex-col gap-3">
            @if ($matches->isNotEmpty())
                @php
                    $groups = [
                        \App\Actions\Patients\PossibleDuplicates::SAME_PERSON => ['Probably the same person', 'red'],
                        \App\Actions\Patients\PossibleDuplicates::SAME_NAME => ['Same name and age', 'amber'],
                        \App\Actions\Patients\PossibleDuplicates::SAME_PHONE => ['Others on this number', 'zinc'],
                    ];
                @endphp
                @foreach ($groups as $type => [$heading, $color])
                    @php $group = $matches->where('match', $type); @endphp
                    @if ($group->isNotEmpty())
                        <flux:card class="flex flex-col gap-2 p-4">
                            <flux:badge size="sm" :color="$color" class="self-start">{{ __($heading) }}</flux:badge>
                            @foreach ($group as $m)
                                <a href="{{ route('patients.show', $m['patient']) }}" target="_blank"
                                    class="rounded-md px-2 py-1.5 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700">
                                    <div class="font-medium">{{ $m['patient']->summary() }}</div>
                                    <div class="text-xs text-zinc-500">
                                        <span class="font-mono">{{ $m['patient']->patient_code }}</span>
                                        @if ($m['patient']->phone) · {{ $m['patient']->phone }} @endif
                                        @unless ($m['patient']->is_active) · {{ __('Inactive') }} @endunless
                                    </div>
                                </a>
                            @endforeach
                        </flux:card>
                    @endif
                @endforeach
            @endif
        </aside>
    </div>
</div>
