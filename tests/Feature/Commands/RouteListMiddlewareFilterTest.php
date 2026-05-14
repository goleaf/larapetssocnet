<?php

use Illuminate\Support\Facades\Artisan;

it('filters route list output by middleware', function (): void {
    $exitCode = Artisan::call('route:list', [
        '--middleware' => 'web',
        '--path' => 'messages',
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())
        ->toContain('messages.store')
        ->toContain('messages.conversation');
});

it('returns no routes when middleware filter does not match', function (): void {
    $exitCode = Artisan::call('route:list', [
        '--middleware' => 'DefinitelyMissingMiddleware',
        '--path' => 'messages',
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain("doesn't have any routes matching");
});
