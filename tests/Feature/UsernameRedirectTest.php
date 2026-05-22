<?php

use App\Models\Identity\User;
use App\Services\UsernameService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 404 for old profile usernames before consulting username redirects', function (): void {
    $user = User::factory()->create(['username' => 'alpha']);

    app(UsernameService::class)->change($user, 'bravo', $user);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => 'alpha', 'tab' => 'posts']))
        ->assertNotFound();
});

it('returns 404 for chained old profile usernames before consulting username redirects', function (): void {
    $user = User::factory()->create(['username' => 'first']);

    app(UsernameService::class)->change($user, 'second', $user, 'test', true);
    app(UsernameService::class)->change($user, 'third', $user, 'test', true);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => 'first']))
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => 'second']))
        ->assertNotFound();
});

it('returns 404 for nonexistent usernames', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => 'missing_user']))
        ->assertNotFound();
});
