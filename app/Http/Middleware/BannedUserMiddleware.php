<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BannedUserMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        try {
            if (! Schema::hasColumn('users', 'is_banned')) {
                return $next($request);
            }
        } catch (Throwable) {
            return $next($request);
        }

        if (! empty($user->is_banned)) {
            return redirect()->route('banned');
        }

        return $next($request);
    }
}
