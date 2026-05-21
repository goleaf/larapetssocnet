<?php

namespace App\Http\Middleware;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountCanAccessApplication
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->trashed()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Your session has ended.');
        }

        if (! empty($user->is_banned) && ! $request->routeIs('banned', 'logout')) {
            return redirect()->route('banned');
        }

        if ($user->scheduled_deletion_at !== null && ! $request->routeIs('account.deletion-pending', 'account.cancel-deletion', 'settings.cancel-deletion', 'logout')) {
            return redirect()->route('account.deletion-pending');
        }

        if ($user->deactivated_at !== null && ! $request->routeIs('account.reactivation', 'account.reactivate', 'logout')) {
            return redirect()->route('account.reactivation');
        }

        if ($this->userIsSuspended($request) && ! $request->routeIs('account.suspended', 'logout')) {
            return redirect()->route('account.suspended');
        }

        return $next($request);
    }

    private function userIsSuspended(Request $request): bool
    {
        $suspendedUntil = $request->user()?->getAttribute('suspended_until');

        return $suspendedUntil instanceof CarbonInterface && $suspendedUntil->isFuture();
    }
}
