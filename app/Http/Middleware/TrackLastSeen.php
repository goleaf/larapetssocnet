<?php

namespace App\Http\Middleware;

use App\Models\Identity\User;
use App\Services\ActiveStatusService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackLastSeen
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            app(ActiveStatusService::class)->touch($user);
        }

        return $next($request);
    }
}
