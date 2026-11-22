<?php

use App\Actions\Settings\UpdateSettings;
use App\Settings\Definitions;
use App\Settings\Settings;
use App\Settings\SettingType;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Clinic settings')] class extends Component {
    /** @var array<string, mixed> field => value */
    public array $form = [];

    public function mount(Settings $settings): void
    {
        foreach ($settings->all() as $key => $value) {
            $this->form[UpdateSettings::field($key)] = $value;
        }
    }

    public function save(UpdateSettings $action): void
    {
        Gate::authorize('settings.manage');

        try {
            $action->handle($this->form);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError("form.{$field}", $messages[0]);
            }

            return;
        }

        $this->mount(app(Settings::class));
        $this->dispatch('settings-saved');
    }

    public function with(): array
    {
        return [
            'groups' => Definitions::grouped(),
            'canManage' => Gate::allows('settings.manage'),
        ];
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-6 p-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Clinic settings') }}</flux:heading>
        <flux:subheading>{{ __('Every change is recorded in the audit history with the old and new value.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <fieldset @disabled(! $canManage) class="flex flex-col gap-6">
            @foreach ($groups as $group => $definitions)
                <flux:card class="flex flex-col gap-5">
                    <flux:heading size="lg">{{ __($group) }}</flux:heading>

                    @foreach ($definitions as $definition)
                        @php($field = 'form.'.UpdateSettings::field($definition->key))

                        @switch($definition->type)
                            @case(SettingType::Boolean)
                                <flux:checkbox wire:model="{{ $field }}" :label="__($definition->label)"
                                    :description="$definition->help ? __($definition->help) : null" />
                                @break

                            @case(SettingType::LongText)
                                <flux:textarea wire:model="{{ $field }}" rows="2" :label="__($definition->label)"
                                    :description="$definition->help ? __($definition->help) : null" />
                                @break

                            @default
                                <flux:field>
                                    <flux:label>{{ __($definition->label) }}</flux:label>
                                    @if ($definition->help)
                                        <flux:description>{{ __($definition->help) }}</flux:description>
                                    @endif
                                    <div class="flex items-center gap-2">
                                        <flux:input wire:model="{{ $field }}"
                                            :type="in_array($definition->type, [SettingType::Integer, SettingType::Decimal]) ? 'number' : 'text'"
                                            :step="$definition->type === SettingType::Decimal ? '0.01' : null"
                                            class="{{ $definition->suffix ? 'max-w-40' : '' }}" />
                                        @if ($definition->suffix)
                                            <span class="text-sm text-zinc-500">{{ __($definition->suffix) }}</span>
                                        @endif
                                    </div>
                                    <flux:error :name="$field" />
                                </flux:field>
                        @endswitch
                    @endforeach
                </flux:card>
            @endforeach
        </fieldset>

        @if ($canManage)
            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">{{ __('Save settings') }}</flux:button>
                <x-action-message on="settings-saved">{{ __('Saved.') }}</x-action-message>
            </div>
        @else
            <flux:text>{{ __('You can view these settings but not change them.') }}</flux:text>
        @endif
    </form>
</div>
