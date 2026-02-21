<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackLastSeen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            try {
                if (Schema::hasColumn('users', 'last_seen_at')) {
                    $user->forceFill([
                        'last_seen_at' => now(),
                    ])->saveQuietly();
                }
            } catch (Throwable) {
                // no-op on schema mismatch
            }
        }

        return $next($request);
    }
}
