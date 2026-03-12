<?php

use App\Models\User;
use App\Services\UsernameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('redirects old usernames to the current username and preserves query strings', function (): void {
    $user = User::factory()->create(['username' => 'alpha']);

    app(UsernameService::class)->change($user, 'bravo', $user);

    $response = $this->get(route('profile.show', ['user' => 'alpha', 'tab' => 'posts']));

    $response->assertRedirect(route('profile.show', ['user' => 'bravo', 'tab' => 'posts']));
});

it('resolves chained username redirects to the latest username', function (): void {
    $user = User::factory()->create(['username' => 'first']);

    app(UsernameService::class)->change($user, 'second', $user, 'test', true);
    app(UsernameService::class)->change($user, 'third', $user, 'test', true);

    $this->get(route('profile.show', ['user' => 'first']))
        ->assertRedirect(route('profile.show', ['user' => 'third']));

    $this->get(route('profile.show', ['user' => 'second']))
        ->assertRedirect(route('profile.show', ['user' => 'third']));
});

it('returns 404 for nonexistent usernames', function (): void {
    $this->get(route('profile.show', ['user' => 'missing_user']))
        ->assertNotFound();
});
