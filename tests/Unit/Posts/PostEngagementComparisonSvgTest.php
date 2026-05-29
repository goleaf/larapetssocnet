<?php

use App\Support\Posts\PostEngagementComparisonSvg;

it('renders a server-side engagement comparison bar chart', function (): void {
    $svg = app(PostEngagementComparisonSvg::class)->render([
        ['label' => 'Views', 'post' => 120, 'average' => 60],
        ['label' => 'Comments', 'post' => 8, 'average' => 3.5],
    ]);

    expect($svg)
        ->toBeString()
        ->toContain('<svg')
        ->toContain('aria-label="Post engagement comparison chart"')
        ->toContain('data-series="post"')
        ->toContain('data-series="average"')
        ->toContain('Views')
        ->toContain('Comments')
        ->toContain('This post vs last 10-post average');
});

it('returns null when there are no chartable metrics', function (): void {
    expect(app(PostEngagementComparisonSvg::class)->render([]))->toBeNull();
});
