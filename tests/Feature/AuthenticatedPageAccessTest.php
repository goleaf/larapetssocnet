<?php

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('redirects guests away from application pages by default', function (string $uri): void {
    $this->get($uri)->assertRedirect(route('login'));
})->with([
    '/',
    '/dashboard',
    '/explore',
    '/search',
    '/explore/pets',
    '/adopt',
    '/adoption',
    '/events',
    '/marketplace',
    '/pets',
    '/tips',
]);

it('keeps application page routes behind authentication middleware', function (string $routeName): void {
    $route = Route::getRoutes()->getByName($routeName);

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('auth')
        ->and($route->gatherMiddleware())->toContain('verified')
        ->and($route->gatherMiddleware())->toContain('banned')
        ->and($route->gatherMiddleware())->toContain('track_last_seen');
})->with([
    'dashboard',
    'search.index',
    'explore.index',
    'pets.explore',
    'pets.adopt',
    'adoption.index',
    'events.index',
    'events.show',
    'events.ics',
    'hashtags.show',
    'posts.show',
    'marketplace.index',
    'marketplace.show',
    'pets.index',
    'pets.show',
    'tips.index',
    'tips.show',
    'tips.helpful',
    'photo-galleries.show',
    'profile.followers',
    'profile.following',
    'profile.redirect',
]);

it('keeps public username profile route outside the auth middleware', function (): void {
    $route = Route::getRoutes()->getByName('profile.show');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->not->toContain('auth')
        ->and($route->gatherMiddleware())->not->toContain('verified');
});

it('allows authenticated users to browse explore', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('explore.index'))
        ->assertOk();
});
