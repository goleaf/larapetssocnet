<?php

use App\Services\ChartService;
use Illuminate\Support\Carbon;

it('returns null for fewer than 2 weight logs', function (): void {
    $service = new ChartService;

    expect($service->weightChart(collect()))->toBeNull();
    expect($service->weightChart(collect([
        (object) ['logged_at' => now(), 'weight_kg' => 5.0],
    ])))->toBeNull();
});

it('returns svg string for 2 or more logs', function (): void {
    $service = new ChartService;

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

it('svg contains polyline element', function (): void {
    $service = new ChartService;

    $logs = collect([
        (object) ['logged_at' => Carbon::parse('2026-01-01'), 'weight_kg' => 4.0],
        (object) ['logged_at' => Carbon::parse('2026-02-01'), 'weight_kg' => 5.0],
    ]);

    $result = $service->weightChart($logs);

    expect($result)->toContain('<polyline');
    expect($result)->toContain('stroke="#10b981"');
});

it('svg contains circle dots', function (): void {
    $service = new ChartService;

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
    $service = new ChartService;

    $logs = collect([
        (object) ['logged_at' => Carbon::parse('2026-01-01'), 'weight_kg' => 5.0],
        (object) ['logged_at' => Carbon::parse('2026-02-01'), 'weight_kg' => 6.0],
    ]);

    $result = $service->weightChart($logs);

    expect($result)->toContain('viewBox="0 0 600 200"');
});
