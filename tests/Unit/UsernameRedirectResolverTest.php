<?php

use App\Models\User;
use App\Services\UsernameRedirectResolver;
use App\Services\UsernameService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves current and redirected usernames', function (): void {
    $user = User::factory()->create(['username' => 'currentname']);

    app(UsernameService::class)->change($user, 'newname', $user);

    $resolver = app(UsernameRedirectResolver::class);

    $current = $resolver->resolve('newname');
    $redirected = $resolver->resolve('currentname');

    expect($current)->not()->toBeNull();
    expect($current['user']->is($user))->toBeTrue();
    expect($current['redirect'])->toBeNull();

    expect($redirected)->not()->toBeNull();
    expect($redirected['user']->is($user))->toBeTrue();
    expect($redirected['redirect'])->not()->toBeNull();
});
