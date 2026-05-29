<?php

namespace App\Services;

use App\Services\Pets\WeightHistorySvg;
use Illuminate\Support\Collection;

class ChartService
{
    public function __construct(private readonly WeightHistorySvg $weightHistorySvg) {}

    /**
     * Generate a pure server-rendered SVG line chart from weight logs.
     * No JS charting library — returns an embeddable SVG string.
     *
     * Each log is expected to have `logged_at` (or `log_date`) + `weight_kg` (or `value`).
     *
     * @return string|null Raw SVG string, or null when there are no valid data points
     */
    public function weightChart(Collection $logs, string $unit = 'kg'): ?string
    {
        $entries = $logs->map(function ($log): array {
            $date = $log->logged_at ?? $log->log_date ?? $log->created_at;
            $value = $log->weight_kg ?? $log->weight_value ?? $log->value ?? null;

            return [
                'date' => $date,
                'weight' => $value,
            ];
        })->all();

        return $this->weightHistorySvg->render($entries, $unit);
    }
}
