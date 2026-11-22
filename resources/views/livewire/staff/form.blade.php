<?php

use App\Actions\Staff\SaveStaff;
use App\Enums\StaffType;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ?Staff $staff = null;

    /** @var array<string, mixed> */
    public array $form = [
        'full_name' => '',
        'type' => 'staff',
        'designation' => '',
        'gender' => '',
        'phone' => '',
        'email' => '',
        'joined_on' => '',
        'left_on' => '',
        'is_active' => true,
        'notes' => '',
        'user_id' => '',
        'doctor' => [
            'registration_no' => '',
            'specialty' => '',
            'qualifications' => '',
            'default_slot_minutes' => '',
            'calendar_color' => '#0ea5e9',
            'is_bookable' => true,
        ],
    ];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $signature = null;

    public function mount(?Staff $staff = null): void
    {
        Gate::authorize('staff.manage');

        if (request()->query('type') === StaffType::Doctor->value) {
            $this->form['type'] = StaffType::Doctor->value;
        }

        if ($staff?->exists) {
            $this->staff = $staff->load('doctor');
            $this->form = array_replace_recursive($this->form, [
                ...$staff->only(['full_name', 'designation', 'gender', 'phone', 'email', 'notes', 'is_active']),
                'type' => $staff->type->value,
                'joined_on' => $staff->joined_on?->toDateString() ?? '',
                'left_on' => $staff->left_on?->toDateString() ?? '',
                'user_id' => (string) ($staff->user_id ?? ''),
                'doctor' => $staff->doctor?->only(['registration_no', 'specialty', 'qualifications', 'default_slot_minutes', 'calendar_color', 'is_bookable']) ?? [],
            ]);
            $this->form = array_map(fn ($v) => $v ?? '', $this->form);
            $this->form['doctor'] = array_map(fn ($v) => $v ?? '', $this->form['doctor']);
        }
    }

    public function save(SaveStaff $action): void
    {
        Gate::authorize('staff.manage');

        try {
            $saved = $action->handle($this->staff, $this->form, $this->signature);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field === 'signature' ? 'signature' : "form.{$field}", $messages[0]);
            }

            return;
        }

        session()->flash('status', $this->staff
            ? __(':name has been updated.', ['name' => $saved->full_name])
            : __(':name has been added.', ['name' => $saved->full_name]));

        $this->redirectRoute('staff.index', navigate: true);
    }

    public function with(): array
    {
        // Logins not yet linked to anyone, plus the one linked to this person.
        $users = User::query()
            ->where(fn ($q) => $q->whereDoesntHave('staff')->when($this->staff?->user_id, fn ($q, $id) => $q->orWhere('id', $id)))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return [
            'types' => StaffType::cases(),
            'users' => $users,
            'isDoctor' => $this->form['type'] === StaffType::Doctor->value,
            'lockedAsDoctor' => $this->staff?->doctor !== null,
        ];
    }

    public function rendering($view): void
    {
        $view->title($this->staff ? __('Edit :name', ['name' => $this->staff->full_name]) : __('Add staff member'));
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-6 p-6">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('staff.index')" wire:navigate>{{ __('Staff') }}</flux:button>
        <flux:heading size="xl" level="1" class="mt-2">
            {{ $staff ? $staff->full_name : __('Add staff member') }}
        </flux:heading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:card class="flex flex-col gap-5">
            <flux:radio.group wire:model.live="form.type" :label="__('Type')" variant="segmented">
                @foreach ($types as $t)
                    <flux:radio :value="$t->value" :label="$t->label()" :disabled="$lockedAsDoctor && $t !== \App\Enums\StaffType::Doctor" />
                @endforeach
            </flux:radio.group>
            @if ($lockedAsDoctor)
                <flux:text class="-mt-3 text-xs">{{ __('Doctors stay doctors because appointments and prescriptions refer to them. Deactivate instead.') }}</flux:text>
            @endif
            <flux:error name="form.type" />

            <flux:input wire:model="form.full_name" :label="__('Full name')" :placeholder="$isDoctor ? 'Dr. ...' : ''" required />

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="form.designation" :label="__('Designation')" :placeholder="$isDoctor ? __('e.g. Consultant, Dental surgeon') : __('e.g. Dental assistant, Receptionist')" />
                <flux:select wire:model="form.gender" :label="__('Gender')">
                    <flux:select.option value="">—</flux:select.option>
                    <flux:select.option value="female">{{ __('Female') }}</flux:select.option>
                    <flux:select.option value="male">{{ __('Male') }}</flux:select.option>
                    <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model="form.phone" :label="__('Mobile')" placeholder="01XXXXXXXXX" />
                <flux:input wire:model="form.email" type="email" :label="__('Email')" />
                <flux:input wire:model="form.joined_on" type="date" :label="__('Joined on')" />
                <flux:input wire:model="form.left_on" type="date" :label="__('Left on')" />
            </div>

            <flux:select wire:model="form.user_id" :label="__('Login account')"
                :description="__('Link the account this person signs in with. Create logins under Administration → Users.')">
                <flux:select.option value="">{{ __('No login') }}</flux:select.option>
                @foreach ($users as $u)
                    <flux:select.option :value="$u->id">{{ $u->name }} ({{ $u->email }})</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="form.notes" rows="2" :label="__('Notes')" />

            <flux:checkbox wire:model="form.is_active" :label="__('Currently working at the clinic')"
                :description="__('Untick when someone leaves. Their name stays on past records.')" />
        </flux:card>

        @if ($isDoctor)
            <flux:card class="flex flex-col gap-5">
                <flux:heading size="lg">{{ __('Doctor details') }}</flux:heading>

                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="form.doctor.registration_no" :label="__('BMDC registration no.')" />
                    <flux:input wire:model="form.doctor.specialty" :label="__('Specialty')" :placeholder="__('e.g. Orthodontics')" />
                    <flux:input wire:model="form.doctor.qualifications" :label="__('Qualifications')" placeholder="BDS, FCPS" class="sm:col-span-2" />

                    <flux:field>
                        <flux:label>{{ __('Usual appointment length') }}</flux:label>
                        <div class="flex items-center gap-2">
                            <flux:input wire:model="form.doctor.default_slot_minutes" type="number" min="5" max="240" class="max-w-28"
                                :placeholder="(string) setting('appointments.default_slot_minutes')" />
                            <span class="text-sm text-zinc-500">{{ __('minutes') }}</span>
                        </div>
                        <flux:description>{{ __('Leave blank to use the clinic default.') }}</flux:description>
                        <flux:error name="form.doctor.default_slot_minutes" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Calendar colour') }}</flux:label>
                        <input type="color" wire:model="form.doctor.calendar_color" class="h-10 w-20 cursor-pointer rounded border border-zinc-300 dark:border-zinc-600" />
                        <flux:error name="form.doctor.calendar_color" />
                    </flux:field>
                </div>

                <flux:checkbox wire:model="form.doctor.is_bookable" :label="__('Patients can be booked with this doctor')" />

                <flux:field>
                    <flux:label>{{ __('Signature for printed prescriptions') }}</flux:label>
                    <flux:description>{{ __('PNG or JPG, up to 1 MB. Stored privately.') }}</flux:description>
                    <input type="file" wire:model="signature" accept="image/png,image/jpeg" class="text-sm" />
                    @if ($staff?->doctor?->signature_path && ! $signature)
                        <flux:text class="text-xs">{{ __('A signature is on file. Upload a new one to replace it.') }}</flux:text>
                    @endif
                    <flux:error name="signature" />
                </flux:field>
            </flux:card>
        @endif

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary">{{ $staff ? __('Save changes') : __('Add staff member') }}</flux:button>
            <flux:button variant="ghost" :href="route('staff.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
