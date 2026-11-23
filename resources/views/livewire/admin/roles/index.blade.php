<?php

use App\Authorization\Permissions;
use App\Enums\RoleName;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new #[Title('Roles & permissions')] class extends Component {
    public function with(): array
    {
        $templates = Permissions::roleTemplates();

        $roles = Role::query()
            ->withCount('users')
            ->with('permissions:id,name')
            ->get()
            ->sortBy(fn (Role $role) => array_search(RoleName::tryFrom($role->name), RoleName::cases(), true))
            ->map(function (Role $role) use ($templates) {
                $current = $role->permissions->pluck('name')->sort()->values()->all();
                $template = collect($templates[$role->name] ?? [])->sort()->values()->all();

                return [
                    'model' => $role,
                    'label' => RoleName::tryFrom($role->name)?->label() ?? $role->name,
                    'permissions' => count($current),
                    'customised' => $current !== $template,
                ];
            });

        return ['roles' => $roles, 'total' => count(Permissions::all())];
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Roles & permissions') }}</flux:heading>
        <flux:subheading>
            {{ __('Each role starts from a template. Changes need a reason and are recorded in the audit history.') }}
        </flux:subheading>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Users') }}</flux:table.column>
            <flux:table.column>{{ __('Permissions') }}</flux:table.column>
            <flux:table.column>{{ __('Template') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($roles as $role)
                <flux:table.row :key="$role['model']->id">
                    <flux:table.cell variant="strong">
                        {{ $role['label'] }}
                        <div class="text-xs font-normal text-zinc-500">{{ $role['model']->name }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $role['model']->users_count }}</flux:table.cell>
                    <flux:table.cell>{{ $role['permissions'] }} / {{ $total }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($role['customised'])
                            <flux:badge size="sm" color="amber">{{ __('Customised') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('As template') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" :icon="auth()->user()->can('roles.manage') ? 'pencil-square' : 'eye'"
                            :href="route('admin.roles.edit', $role['model'])" wire:navigate>
                            @can('roles.manage') {{ __('Edit') }} @else {{ __('View') }} @endcan
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
