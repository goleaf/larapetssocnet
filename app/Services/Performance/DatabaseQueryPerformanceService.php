<?php

declare(strict_types=1);

namespace App\Services\Performance;

use Illuminate\Database\Events\QueryExecuted;

final class DatabaseQueryPerformanceService
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $capturedQueries = [];

    /**
     * @var array<string, string>
     */
    private array $requestContext = [];

    public function startRequest(string $requestId, array $context): void
    {
        $this->requestContext = [
            'request_id' => $requestId,
            'route_name' => (string) ($context['route_name'] ?? ''),
            'route_path' => (string) ($context['route_path'] ?? ''),
            'route_action' => (string) ($context['route_action'] ?? ''),
            'livewire_component' => (string) ($context['livewire_component'] ?? ''),
        ];

        $this->capturedQueries = [];
    }

    public function captureQuery(QueryExecuted $query, array $context): void
    {
        if (! $this->isActive()) {
            return;
        }

        $rawSql = (string) $query->sql;

        $this->capturedQueries[] = [
            'sql' => $rawSql,
            'normalized_sql' => $this->normalizeSql($rawSql),
            'sql_hash' => md5((string) $rawSql),
            'time_ms' => (float) $query->time,
            'connection_name' => (string) $query->connectionName,
            'readwrite_type' => (string) ($query->readWriteType ?? ''),
            'request' => $this->requestContext,
            'in_blade' => (bool) ($context['in_blade'] ?? false),
            'in_livewire_render' => (bool) ($context['in_livewire_render'] ?? false),
            'location_signature' => (string) ($context['location_signature'] ?? ''),
            'bindings_count' => is_array($query->bindings) ? count($query->bindings) : 0,
            'binding_types' => $this->bindingTypes($query->bindings ?? []),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function finishRequest(): array
    {
        $queries = $this->capturedQueries;

        $this->capturedQueries = [];
        $this->requestContext = [];

        return $queries;
    }

    public function isActive(): bool
    {
        return $this->requestContext !== [];
    }

    /**
     * @param  list<array<string, mixed>>  $queries
     * @return array<string, mixed>
     */
    public function summarizeQueries(
        array $queries,
        int $slowLikeThresholdMs,
        int $sortThresholdMs,
        int $duplicateThreshold,
        int $loopThreshold,
    ): array {
        $queryCount = count($queries);
        $totalTimeMs = 0.0;
        $duplicates = [];
        $loopCandidates = [];
        $slowLikeQueries = [];
        $sortingCandidates = [];
        $nPlusOneCandidates = [];
        $bladeQueries = 0;
        $livewireRenderQueries = 0;
        $indexRiskCandidates = [];

        foreach ($queries as $query) {
            $time = (float) ($query['time_ms'] ?? 0.0);
            $totalTimeMs += $time;

            if (! empty($query['in_blade'])) {
                $bladeQueries++;
            }

            if (! empty($query['in_livewire_render'])) {
                $livewireRenderQueries++;
            }

            $normalizedSql = (string) ($query['normalized_sql'] ?? '');
            $lowerSql = strtolower($normalizedSql);

            if ($normalizedSql === '') {
                continue;
            }

            $signature = (string) ($query['sql_hash'] ?? md5($normalizedSql));
            $query['signature'] = $signature;
            $duplicates[$signature] = ($duplicates[$signature] ?? 0) + 1;

            $locationSignature = (string) ($query['location_signature'] ?? '');
            if ($locationSignature !== '') {
                $loopKey = $signature.'|'.$locationSignature;
                $loopCandidates[$loopKey] = ($loopCandidates[$loopKey] ?? 0) + 1;
            }

            if (str_contains($lowerSql, ' like ') && $time >= $slowLikeThresholdMs) {
                $slowLikeQueries[] = [
                    'sql_hash' => $signature,
                    'sql' => (string) ($query['sql'] ?? ''),
                    'time_ms' => $time,
                    'risk_score' => 3,
                ];
            }

            if (str_contains($lowerSql, ' order by ') && $time >= $sortThresholdMs) {
                $sortingCandidates[] = [
                    'sql_hash' => $signature,
                    'sql' => (string) ($query['sql'] ?? ''),
                    'time_ms' => $time,
                    'likely_missing_index' => ! str_contains($lowerSql, ' limit ') || ! str_contains($lowerSql, ' idx_'),
                    'risk_score' => 3,
                ];
            }

            $indexRiskScore = 1;
            if (str_contains($lowerSql, ' like ')) {
                $indexRiskScore++;
            }

            if (str_contains($lowerSql, ' order by ') && ! str_contains($lowerSql, ' idx_') && str_contains($lowerSql, ' desc')) {
                $indexRiskScore++;
            }

            if ($indexRiskScore >= 2 && $time >= 100.0) {
                $indexRiskCandidates[] = [
                    'sql_hash' => $signature,
                    'sql' => (string) ($query['sql'] ?? ''),
                    'risk_score' => $indexRiskScore,
                ];
            }
        }

        $duplicateGroups = [];
        foreach ($duplicates as $querySignature => $count) {
            if ($count >= $duplicateThreshold) {
                $querySql = '';
                foreach ($queries as $query) {
                    if ((string) ($query['sql_hash'] ?? md5((string) $query['sql'])) === $querySignature) {
                        $querySql = (string) ($query['sql'] ?? '');

                        break;
                    }
                }

                $duplicateGroups[] = [
                    'count' => $count,
                    'signature' => $querySignature,
                    'sql' => $querySql,
                ];
            }
        }

        foreach ($loopCandidates as $loopKey => $count) {
            if ($count >= $loopThreshold) {
                [$queryHash] = explode('|', $loopKey, 2);

                $loopSql = '';
                foreach ($queries as $query) {
                    if ((string) ($query['sql_hash'] ?? md5((string) $query['sql'])) === $queryHash) {
                        $loopSql = (string) ($query['sql'] ?? '');

                        break;
                    }
                }

                $nPlusOneCandidates[] = [
                    'count' => $count,
                    'signature' => $queryHash,
                    'sql' => $loopSql,
                ];
            }
        }

        return [
            'query_count' => $queryCount,
            'total_time_ms' => $totalTimeMs,
            'duplicate_queries' => array_values($duplicateGroups),
            'loop_queries' => array_values($nPlusOneCandidates),
            'slow_like_queries' => array_values($slowLikeQueries),
            'sorting_without_indexes' => array_values(array_filter(
                $sortingCandidates,
                static fn (array $query): bool => (bool) ($query['likely_missing_index'] ?? false),
            )),
            'blade_queries' => $bladeQueries,
            'livewire_render_queries' => $livewireRenderQueries,
            'missing_index_risks' => array_values($indexRiskCandidates),
        ];
    }

    /**
     * @param  array<mixed>  $bindings
     * @return list<string>
     */
    private function bindingTypes(array $bindings): array
    {
        return array_map(
            fn (mixed $binding): string => match (true) {
                $binding === null => 'null',
                is_bool($binding) => 'bool',
                is_int($binding) => 'int',
                is_float($binding) => 'float',
                is_string($binding) => 'string',
                is_array($binding) => 'array',
                is_object($binding) => 'object:'.get_debug_type($binding),
                default => gettype($binding),
            },
            $bindings,
        );
    }

    private function normalizeSql(string $sql): string
    {
        $trimmed = trim(preg_replace('/\s+/', ' ', strtolower($sql)));

        return $trimmed === '' ? '' : $trimmed;
    }
}
