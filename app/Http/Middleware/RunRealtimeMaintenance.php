<?php

namespace App\Http\Middleware;

use App\Services\Maintenance\MaintenanceTaskService;
use Closure;
use Illuminate\Http\Request;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class RunRealtimeMaintenance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->is('admin/maintenance*')) {
            return;
        }

        try {
            app(MaintenanceTaskService::class)->runRealtimeDueTasks();
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
