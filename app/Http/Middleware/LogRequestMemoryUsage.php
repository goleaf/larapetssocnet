<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequestMemoryUsage
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment(['local', 'staging'])) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $startMemory = memory_get_usage(true);

        $response = $next($request);

        Log::debug('Request memory usage', [
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
            'memory_mb' => round(memory_get_usage(true) / 1_048_576, 2),
            'memory_delta_mb' => round((memory_get_usage(true) - $startMemory) / 1_048_576, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1_048_576, 2),
        ]);

        return $response;
    }
}
