<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Request;

/**
 * Writes sign-in activity to the "auth" audit log.
 * Registered in AppServiceProvider. Methods are named on*() rather than handle*()
 * so Laravel's listener auto-discovery doesn't register them a second time.
 */
class AuthActivitySubscriber
{
    public function onLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        activity('auth')->event('login')
            ->performedOn($event->user)->causedBy($event->user)
            ->withProperties($this->context())
            ->log('Logged in');
    }

    public function onLogout(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        activity('auth')->event('logout')
            ->performedOn($event->user)->causedBy($event->user)
            ->withProperties($this->context())
            ->log('Logged out');
    }

    public function onFailed(Failed $event): void
    {
        $logger = activity('auth')->event('login_failed')
            ->withProperties([...$this->context(), 'email' => $event->credentials['email'] ?? null]);

        if ($event->user instanceof User) {
            $logger->performedOn($event->user);
        }

        $logger->causedByAnonymous()->log('Failed login attempt');
    }

    /** @return array<string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Logout::class => 'onLogout',
            Failed::class => 'onFailed',
        ];
    }

    /** @return array{ip: string|null, user_agent: string|null} */
    private function context(): array
    {
        return [
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255) ?: null,
        ];
    }
}
