<?php

use Illuminate\Support\Facades\Cache;

it('pauses queue processing using the queue pause command', function (): void {
    $cacheKey = 'illuminate:queue:paused:database:default';
    Cache::forget($cacheKey);

    $this->artisan('queue:pause', [
        'queue' => 'database:default',
    ])
        ->expectsOutputToContain('has been paused')
        ->assertExitCode(0);

    expect(Cache::get($cacheKey))->toBeTrue();
});

it('resumes queue processing using the queue continue command', function (): void {
    $cacheKey = 'illuminate:queue:paused:database:default';
    Cache::put($cacheKey, true, now()->addMinute());

    $this->artisan('queue:continue', [
        'queue' => 'database:default',
    ])
        ->expectsOutputToContain('has been resumed')
        ->assertExitCode(0);

    expect(Cache::get($cacheKey))->toBeNull();
});
