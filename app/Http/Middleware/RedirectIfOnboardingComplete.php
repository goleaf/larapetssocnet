<?php

namespace App\Http\Middleware;

use App\Models\Identity\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfOnboardingComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasCompletedOnboarding()) {
            return redirect()->route('feed.index');
        }

        return $next($request);
    }
}
