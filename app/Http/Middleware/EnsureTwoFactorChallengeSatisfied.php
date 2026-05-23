<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorChallengeSatisfied
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->two_factor_secret === null) {
            return $next($request);
        }

        $pendingUserId = $request->session()->get('auth.two_factor_pending_user_id');

        if ((string) $pendingUserId === (string) $user->getKey() && ! $request->routeIs('two-factor.*', 'logout')) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
