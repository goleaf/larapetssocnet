<?php

use Illuminate\Support\Facades\Cache;

it('pauses a queue for a limited time', function (): void {
    $cacheKey = 'illuminate:queue:paused:database:default';
    Cache::forget($cacheKey);

    $this->artisan('queue:pause-for', [
        'queue' => 'database:default',
        '--seconds' => 2,
    ])
        ->expectsOutputToContain('has been paused for 2 seconds')
        ->assertExitCode(0);

    expect(Cache::get($cacheKey))->toBeTrue();

    $this->travel(3)->seconds();

    expect(Cache::get($cacheKey))->toBeNull();
});

it('rejects non-positive pause durations', function (): void {
    $cacheKey = 'illuminate:queue:paused:database:default';
    Cache::forget($cacheKey);

    $this->artisan('queue:pause-for', [
        'queue' => 'database:default',
        '--seconds' => 0,
    ])
        ->expectsOutputToContain('must be at least 1')
        ->assertExitCode(1);

    expect(Cache::get($cacheKey))->toBeNull();
});
