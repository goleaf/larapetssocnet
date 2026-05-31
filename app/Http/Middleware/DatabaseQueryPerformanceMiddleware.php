<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Performance\DatabaseQueryPerformanceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DatabaseQueryPerformanceMiddleware
{
    public function __construct(
        private readonly DatabaseQueryPerformanceService $auditor,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            ! $request->isMethod('GET')
            || ! app()->environment(['local', 'testing'])
            || ! (bool) config('database-performance.enabled', true)
            || ! (bool) config('database-performance.query_count.enabled', false)
        ) {
            return $next($request);
        }

        $requestId = (string) $request->id();

        $this->auditor->startRequest($requestId, [
            'route_name' => (string) $request->route()?->getName(),
            'route_path' => $request->path(),
            'route_action' => (string) $request->route()?->getActionName(),
            'livewire_component' => (string) ($request->route()?->getAction('livewire_component') ?? ''),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $next($request);

        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        $querySnapshots = $this->auditor->finishRequest();

        $queryCount = count($querySnapshots);
        $queryTimeMs = 0.0;

        if ($querySnapshots !== []) {
            $summary = $this->auditor->summarizeQueries(
                $querySnapshots,
                (int) config('database-performance.audit.slow_like_ms', 200),
                (int) config('database-performance.audit.sort_candidate_ms', 250),
                (int) config('database-performance.query_count.duplicate_threshold', 2),
                (int) config('database-performance.query_count.loop_threshold', 3),
            );

            $queryCount = (int) ($summary['query_count'] ?? $queryCount);
            $queryTimeMs = (float) ($summary['total_time_ms'] ?? 0.0);
            $duplicateGroups = (array) ($summary['duplicate_queries'] ?? []);
            $loopGroups = (array) ($summary['loop_queries'] ?? []);

            if (! empty($summary['missing_index_risks'])) {
                $this->logAuditorWarnings($request, 'Potential missing-index query pattern detected', [
                    'pattern_count' => count((array) $summary['missing_index_risks']),
                ]);
            }

            if (! empty($summary['blade_queries'])) {
                $this->logAuditorWarnings($request, 'Database queries were executed during view rendering', [
                    'query_count' => (int) $summary['blade_queries'],
                ]);
            }

            if (! empty($summary['livewire_render_queries'])) {
                $this->logAuditorWarnings($request, 'Database queries were executed inside Livewire render hooks', [
                    'query_count' => (int) $summary['livewire_render_queries'],
                ]);
            }

            if (! empty($summary['loop_queries'])) {
                $this->logAuditorWarnings($request, 'Possible in-loop database query pattern detected', [
                    'loop_query_groups' => count($loopGroups),
                ]);
            }

            if (! empty($summary['duplicate_queries'])) {
                $this->logAuditorWarnings($request, 'Duplicate SQL signature detected in request', [
                    'duplicate_query_groups' => count($duplicateGroups),
                ]);
            }
        }

        if ($querySnapshots === []) {
            foreach ($queryLog as $queryEntry) {
                $queryTimeMs += (float) ($queryEntry['time'] ?? 0.0);
            }

            $queryCount = count($queryLog);
        }

        $maxQueries = (int) config('database-performance.query_count.max_queries', 120);

        if ($queryCount > $maxQueries) {
            Log::warning('Database query budget exceeded', [
                'request_id' => $requestId,
                'route' => $request->route()?->getName(),
                'path' => $request->path(),
                'query_count' => $queryCount,
                'max_queries' => $maxQueries,
            ]);
        }

        if ((float) config('database-performance.cumulative.slow_total_ms', 500) > 0.0 && $queryTimeMs > 0.0) {
            $maxTotalMs = (float) config('database-performance.cumulative.slow_total_ms', 500);

            if ($queryTimeMs > $maxTotalMs) {
                Log::warning('Query cumulative threshold exceeded for request', [
                    'request_id' => $requestId,
                    'route' => $request->route()?->getName(),
                    'path' => $request->path(),
                    'query_time_ms' => round($queryTimeMs, 2),
                    'max_query_time_ms' => $maxTotalMs,
                ]);
            }
        }

        $response->headers->set('X-DB-Query-Count', (string) $queryCount);
        $response->headers->set('X-DB-Query-Time-Ms', (string) round($queryTimeMs, 2));

        return $response;
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function logAuditorWarnings(Request $request, string $message, array $metrics): void
    {
        if (! (bool) config('database-performance.audit.enabled', true)) {
            return;
        }

        Log::warning($message, array_merge([
            'request_id' => (string) $request->id(),
            'route' => $request->route()?->getName(),
            'path' => $request->path(),
        ], $metrics));
    }
}
