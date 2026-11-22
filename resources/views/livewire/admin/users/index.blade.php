<?php

use App\Actions\Users\SetUserActive;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $status = 'active';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function setActive(int $userId, bool $active, SetUserActive $action): void
    {
        Gate::authorize('users.manage');

        $user = User::findOrFail($userId);
        $action->handle(auth()->user(), $user, $active);

        session()->flash('status', $active
            ? __(':name has been reactivated.', ['name' => $user->name])
            : __(':name has been deactivated.', ['name' => $user->name]));
    }

    public function with(): array
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search !== '', function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when(RoleName::tryFrom($this->role), fn ($query, RoleName $role) => $query->role($role->value))
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(20);

        return [
            'users' => $users,
            'roles' => RoleName::cases(),
            'canManage' => Gate::allows('users.manage'),
        ];
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            <flux:subheading>{{ __('Staff login accounts and their roles. Accounts are deactivated, never deleted.') }}</flux:subheading>
        </div>

        @if ($canManage)
            <flux:button variant="primary" icon="plus" :href="route('admin.users.create')" wire:navigate>
                {{ __('New user') }}
            </flux:button>
        @endif
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    @error('user')
        <flux:callout variant="danger" icon="x-circle" :heading="$message" />
    @enderror

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, email, phone')" />

        <flux:select wire:model.live="role">
            <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
            @foreach ($roles as $r)
                <flux:select.option :value="$r->value">{{ $r->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="status">
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Deactivated') }}</flux:select.option>
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$users">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email / phone') }}</flux:table.column>
            <flux:table.column>{{ __('Roles') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Last login') }}</flux:table.column>
            @if ($canManage)
                <flux:table.column />
            @endif
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell variant="strong">
                        {{ $user->name }}
                        @if ($user->is(auth()->user()))
                            <span class="text-xs text-zinc-500">({{ __('you') }})</span>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <div>{{ $user->email }}</div>
                        <div class="text-xs text-zinc-500">{{ $user->phone }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($user->roles as $userRole)
                                <flux:badge size="sm">{{ RoleName::tryFrom($userRole->name)?->label() ?? $userRole->name }}</flux:badge>
                            @endforeach
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($user->is_active)
                            <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('Deactivated') }}</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-500">
                        {{ $user->last_login_at?->format('d M Y, h:i A') ?? '—' }}
                    </flux:table.cell>

                    @if ($canManage)
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                @can('audit.view')
                                    <flux:button size="sm" variant="ghost" icon="clock"
                                        :href="route('admin.audit.index', ['subjectType' => $user->getMorphClass(), 'subjectId' => $user->id])" wire:navigate>
                                        {{ __('History') }}
                                    </flux:button>
                                @endcan
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('admin.users.edit', $user)" wire:navigate>
                                    {{ __('Edit') }}
                                </flux:button>

                                @unless ($user->is(auth()->user()))
                                    @if ($user->is_active)
                                        <flux:button size="sm" variant="ghost" icon="no-symbol"
                                            wire:click="setActive({{ $user->id }}, false)"
                                            wire:confirm="{{ __('Deactivate :name? They will be signed out and unable to log in.', ['name' => $user->name]) }}">
                                            {{ __('Deactivate') }}
                                        </flux:button>
                                    @else
                                        <flux:button size="sm" variant="ghost" icon="arrow-path"
                                            wire:click="setActive({{ $user->id }}, true)">
                                            {{ __('Reactivate') }}
                                        </flux:button>
                                    @endif
                                @endunless
                            </div>
                        </flux:table.cell>
                    @endif
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">{{ __('No users found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
