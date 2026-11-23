<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a user out after `security.idle_timeout_minutes` without any request.
 * Clinic computers are shared, so an unattended screen must not stay signed in.
 */
class SignOutIdleSessions
{
    public const SESSION_KEY = 'last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $session = $request->session();
        $last = $session->get(self::SESSION_KEY);
        $limitSeconds = (int) setting('security.idle_timeout_minutes') * 60;

        if ($last !== null && now()->getTimestamp() - (int) $last > $limitSeconds) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('login')
                ->with('status', __('You were signed out after a period of inactivity.'));
        }

        $session->put(self::SESSION_KEY, now()->getTimestamp());

        return $next($request);
    }
}
