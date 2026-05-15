<?php

use App\Services\Maintenance\QueuePauseService;
use Illuminate\Support\Facades\Cache;

it('pauses a queue for a limited time', function (): void {
    $cacheKey = 'illuminate:queue:paused:database:default';
    Cache::forget($cacheKey);

    $result = app(QueuePauseService::class)->pauseFor('database:default', 2);

    expect($result->metrics['seconds'])->toBe(2)
        ->and(Cache::get($cacheKey))->toBeTrue();

    $this->travel(3)->seconds();

    expect(Cache::get($cacheKey))->toBeNull();
});

it('rejects non-positive pause durations', function (): void {
    $cacheKey = 'illuminate:queue:paused:database:default';
    Cache::forget($cacheKey);

    expect(fn () => app(QueuePauseService::class)->pauseFor('database:default', 0))
        ->toThrow(InvalidArgumentException::class, 'Pause duration must be at least 1 second.');

    expect(Cache::get($cacheKey))->toBeNull();
});
