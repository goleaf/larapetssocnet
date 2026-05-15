<?php

use App\Services\Maintenance\QueuePauseService;
use Illuminate\Support\Facades\Cache;

it('pauses queue processing using the maintenance service', function (): void {
    $cacheKey = 'illuminate:queue:paused:database:default';
    Cache::forget($cacheKey);

    $result = app(QueuePauseService::class)->pause('database:default');

    expect($result->task)->toBe('pause-queue')
        ->and(Cache::get($cacheKey))->toBeTrue();
});

it('resumes queue processing using the maintenance service', function (): void {
    $cacheKey = 'illuminate:queue:paused:database:default';
    Cache::put($cacheKey, true, now()->addMinute());

    $result = app(QueuePauseService::class)->resume('database:default');

    expect($result->task)->toBe('resume-queue')
        ->and(Cache::get($cacheKey))->toBeNull();
});
