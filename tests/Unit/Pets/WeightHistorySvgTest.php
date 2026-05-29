<?php

use App\Services\Pets\WeightHistorySvg;

it('returns null when no weight points are available', function (): void {
    expect(app(WeightHistorySvg::class)->render([]))->toBeNull();
});

it('renders a single weight point as a labeled dot without a chart line', function (): void {
    $svg = app(WeightHistorySvg::class)->render([
        ['date' => '2026-05-29', 'weight' => '12.50'],
    ], 'lbs');

    expect($svg)
        ->toContain('<circle')
        ->toContain('12.5 lbs')
        ->toContain('2026')
        ->not->toContain('stroke="var(--color-paw, #b46139)"');
});

it('sorts weight points and renders a smooth bezier path', function (): void {
    $svg = app(WeightHistorySvg::class)->render([
        ['date' => '2026-02-01', 'weight' => 14.3],
        ['date' => '2024-01-01', 'weight' => 10.1],
        ['date' => '2025-01-01', 'weight' => 12.4],
    ]);

    expect($svg)
        ->toContain(' C ')
        ->toContain('10.1 kg on 2024-01-01')
        ->toContain('12.4 kg on 2025-01-01')
        ->toContain('14.3 kg on 2026-02-01');
});
