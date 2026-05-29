<?php

use App\Services\ChartService;
use App\Services\Pets\WeightHistorySvg;
use Illuminate\Support\Carbon;

it('returns null when no weight logs are available', function (): void {
    $service = chartService();

    expect($service->weightChart(collect()))->toBeNull();
});

it('returns svg string for 2 or more logs', function (): void {
    $service = chartService();

    $logs = collect([
        (object) ['logged_at' => Carbon::parse('2026-01-01'), 'weight_kg' => 4.5],
        (object) ['logged_at' => Carbon::parse('2026-01-15'), 'weight_kg' => 5.0],
        (object) ['logged_at' => Carbon::parse('2026-02-01'), 'weight_kg' => 5.3],
    ]);

    $result = $service->weightChart($logs);

    expect($result)->toBeString();
    expect($result)->toContain('<svg');
    expect($result)->toContain('</svg>');
});

it('svg contains a smooth path for multiple points', function (): void {
    $service = chartService();

    $logs = collect([
        (object) ['logged_at' => Carbon::parse('2026-01-01'), 'weight_kg' => 4.0],
        (object) ['logged_at' => Carbon::parse('2026-02-01'), 'weight_kg' => 5.0],
    ]);

    $result = $service->weightChart($logs);

    expect($result)->toContain('<path d="M ');
    expect($result)->toContain('stroke="var(--color-paw, #b46139)"');
});

it('svg contains circle dots', function (): void {
    $service = chartService();

    $logs = collect([
        (object) ['logged_at' => Carbon::parse('2026-01-01'), 'weight_kg' => 3.0],
        (object) ['logged_at' => Carbon::parse('2026-02-01'), 'weight_kg' => 4.0],
        (object) ['logged_at' => Carbon::parse('2026-03-01'), 'weight_kg' => 4.5],
    ]);

    $result = $service->weightChart($logs);

    expect($result)->toContain('<circle');
    // Should have 3 dots for 3 data points
    expect(substr_count($result, '<circle'))->toBe(3);
});

it('svg has correct viewbox attribute', function (): void {
    $service = chartService();

    $logs = collect([
        (object) ['logged_at' => Carbon::parse('2026-01-01'), 'weight_kg' => 5.0],
        (object) ['logged_at' => Carbon::parse('2026-02-01'), 'weight_kg' => 6.0],
    ]);

    $result = $service->weightChart($logs);

    expect($result)->toContain('viewBox="0 0 600 200"');
});

function chartService(): ChartService
{
    return new ChartService(new WeightHistorySvg);
}
