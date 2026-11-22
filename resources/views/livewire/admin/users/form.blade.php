<?php

use App\Actions\Users\CreateUser;
use App\Actions\Users\UpdateUser;
use App\Authorization\Permissions;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component {
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** @var list<string> */
    public array $roles = [];

    public function mount(?User $user = null): void
    {
        Gate::authorize('users.manage');

        if ($user?->exists) {
            $this->user = $user;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->phone = (string) $user->phone;
            $this->roles = $user->roles->pluck('name')->all();
        }
    }

    public function save(CreateUser $create, UpdateUser $update): void
    {
        Gate::authorize('users.manage');

        $input = [
            'name' => trim($this->name),
            'email' => strtolower(trim($this->email)),
            'phone' => trim($this->phone) ?: null,
            'password' => $this->password ?: null,
            'password_confirmation' => $this->password_confirmation ?: null,
            'roles' => array_values($this->roles),
        ];

        if ($this->user) {
            $saved = $update->handle(auth()->user(), $this->user, $input);
            $message = __(':name has been updated.', ['name' => $saved->name]);
        } else {
            $saved = $create->handle($input);
            $message = __(':name has been created.', ['name' => $saved->name]);
        }

        session()->flash('status', $message);
        $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function with(): array
    {
        $templates = Permissions::roleTemplates();

        return [
            'roleOptions' => collect(RoleName::cases())->map(fn (RoleName $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'count' => count($templates[$role->value] ?? []),
            ]),
        ];
    }

    public function rendering($view): void
    {
        $view->title($this->user ? __('Edit user') : __('New user'));
    }
}; ?>

<div class="flex max-w-2xl flex-col gap-6 p-6">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('admin.users.index')" wire:navigate>
            {{ __('Users') }}
        </flux:button>
        <flux:heading size="xl" level="1" class="mt-2">
            {{ $user ? __('Edit :name', ['name' => $user->name]) : __('New user') }}
        </flux:heading>
        @if ($user && ! $user->is_active)
            <flux:badge color="zinc" class="mt-2">{{ __('Deactivated') }}</flux:badge>
        @endif
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:input wire:model="name" :label="__('Full name')" required autofocus />

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="email" type="email" :label="__('Email (used to log in)')" required autocomplete="off" />
            <flux:input wire:model="phone" :label="__('Phone')" placeholder="01XXXXXXXXX" />
        </div>

        <flux:checkbox.group wire:model="roles" :label="__('Roles')" :description="__('A user may have more than one role. Permissions are combined.')">
            @foreach ($roleOptions as $option)
                <flux:checkbox :value="$option['value']" :label="$option['label']"
                    :description="trans_choice(':count permission|:count permissions', $option['count'], ['count' => $option['count']])" />
            @endforeach
        </flux:checkbox.group>

        <flux:separator />

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="password" type="password" autocomplete="new-password"
                :label="$user ? __('New password') : __('Password')"
                :description="$user ? __('Leave blank to keep the current password.') : null"
                :required="! $user" />
            <flux:input wire:model="password_confirmation" type="password" autocomplete="new-password"
                :label="__('Confirm password')" :required="! $user" />
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary">{{ $user ? __('Save changes') : __('Create user') }}</flux:button>
            <flux:button variant="ghost" :href="route('admin.users.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
