<?php

use App\Actions\Roles\UpdateRolePermissions;
use App\Authorization\Permissions;
use App\Enums\RoleName;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new class extends Component {
    public Role $role;

    /** @var list<string> */
    public array $selected = [];

    public string $reason = '';

    public function mount(Role $role): void
    {
        $this->role = $role;
        $this->selected = $role->permissions()->pluck('name')->sort()->values()->all();
    }

    public function resetToTemplate(): void
    {
        Gate::authorize('roles.manage');

        $this->selected = Permissions::roleTemplates()[$this->role->name] ?? [];
    }

    public function save(UpdateRolePermissions $action): void
    {
        Gate::authorize('roles.manage');

        $result = $action->handle($this->role, array_values($this->selected), $this->reason);

        if ($result['added'] === [] && $result['removed'] === []) {
            $this->addError('permissions', __('Nothing changed.'));

            return;
        }

        session()->flash('status', __(':role updated: :added added, :removed removed.', [
            'role' => $this->label(),
            'added' => count($result['added']),
            'removed' => count($result['removed']),
        ]));

        $this->redirectRoute('admin.roles.index', navigate: true);
    }

    public function label(): string
    {
        return RoleName::tryFrom($this->role->name)?->label() ?? $this->role->name;
    }

    public function with(): array
    {
        $current = $this->role->permissions()->pluck('name')->all();
        $template = Permissions::roleTemplates()[$this->role->name] ?? [];

        return [
            'groups' => Permissions::grouped(),
            'current' => array_flip($current),
            'template' => array_flip($template),
            'canManage' => Gate::allows('roles.manage'),
            'locked' => $this->role->name === RoleName::SystemAdmin->value ? array_flip(Permissions::ADMIN_REQUIRED) : [],
            'pendingAdded' => array_values(array_diff($this->selected, $current)),
            'pendingRemoved' => array_values(array_diff($current, $this->selected)),
        ];
    }

    public function rendering($view): void
    {
        $view->title(__('Role: :role', ['role' => $this->label()]));
    }
}; ?>

<div class="flex max-w-5xl flex-col gap-6 p-6">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('admin.roles.index')" wire:navigate>
            {{ __('Roles & permissions') }}
        </flux:button>
        <flux:heading size="xl" level="1" class="mt-2">{{ $this->label() }}</flux:heading>
        <flux:subheading>
            {{ __('Ticked = this role has the permission. A dot marks a difference from the starting template.') }}
        </flux:subheading>
    </div>

    @error('permissions')
        <flux:callout variant="danger" icon="x-circle" :heading="$message" />
    @enderror

    <form wire:submit="save" class="flex flex-col gap-6">
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($groups as $groupLabel => $permissions)
                <flux:card class="flex flex-col gap-3">
                    <flux:heading size="sm">{{ __($groupLabel) }}</flux:heading>

                    @foreach ($permissions as $permission => $description)
                        @php($differsFromTemplate = in_array($permission, $selected, true) !== isset($template[$permission]))
                        <label class="flex items-start gap-3 text-sm" wire:key="perm-{{ $permission }}">
                            <input type="checkbox" value="{{ $permission }}" wire:model.live="selected"
                                class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600"
                                @disabled(! $canManage || isset($locked[$permission])) />
                            <span class="flex-1">
                                <span class="text-zinc-800 dark:text-zinc-100">{{ __($description) }}</span>
                                <span class="block font-mono text-xs text-zinc-500">
                                    {{ $permission }}
                                    @if (isset($locked[$permission]))
                                        · {{ __('required for administrators') }}
                                    @endif
                                </span>
                            </span>
                            @if ($differsFromTemplate)
                                <span class="mt-1 size-2 shrink-0 rounded-full bg-amber-500" title="{{ __('Differs from template') }}"></span>
                            @endif
                        </label>
                    @endforeach
                </flux:card>
            @endforeach
        </div>

        @if ($canManage)
            <flux:card class="sticky bottom-4 flex flex-col gap-4">
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                    <span><strong class="text-green-600">+{{ count($pendingAdded) }}</strong> {{ __('to add') }}</span>
                    <span><strong class="text-red-600">−{{ count($pendingRemoved) }}</strong> {{ __('to remove') }}</span>
                    @if ($pendingAdded || $pendingRemoved)
                        <span class="text-zinc-500">
                            {{ implode(', ', array_merge(array_map(fn ($p) => "+$p", $pendingAdded), array_map(fn ($p) => "−$p", $pendingRemoved))) }}
                        </span>
                    @endif
                </div>

                <flux:textarea wire:model="reason" rows="2" :label="__('Reason for change')"
                    :placeholder="__('e.g. Owner approved receptionists viewing finance reports (meeting 22 Nov)')" />

                <div class="flex flex-wrap gap-3">
                    <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                    <flux:button type="button" variant="ghost" icon="arrow-uturn-left" wire:click="resetToTemplate">
                        {{ __('Reset to template') }}
                    </flux:button>
                </div>
            </flux:card>
        @endif
    </form>
</div>
