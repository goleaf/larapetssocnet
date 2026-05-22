<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;

uses(RefreshDatabase::class);

it('routes username profiles through a full-page livewire component', function (): void {
    $route = Route::getRoutes()->getByName('profile.show');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('@{user}')
        ->and($route->getAction('livewire_component'))->toBe('pages.profile.show')
        ->and($route->getActionName())->toContain('LivewirePageController')
        ->and(route('profile.show', ['user' => 'social_handle'], false))->toBe('/@social_handle');
});

it('registers the public profile route after application named routes', function (): void {
    $namedRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => is_string($route->getName()) && $route->getName() !== '')
        ->values();

    $profileIndex = $namedRoutes->search(fn ($route): bool => $route->getName() === 'profile.show');

    expect($profileIndex)->not->toBeFalse();

    $applicationRoutesAfterProfile = $namedRoutes
        ->slice((int) $profileIndex + 1)
        ->reject(fn ($route): bool => str_starts_with((string) $route->getName(), 'storage.'))
        ->values();

    expect($applicationRoutesAfterProfile->map(fn ($route): ?string => $route->getName())->all())->toBe([]);
});

it('renders the public profile page from the livewire route', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Livewire Routed Member',
        'username' => 'livewire_route',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->get('/@livewire_route')
        ->assertOk()
        ->assertSee('data-ui="profile-shell"', false)
        ->assertSee('Livewire Routed Member')
        ->assertSee('@livewire_route');
});

it('renders the profile page component directly', function (): void {
    if (! $this->app->providerIsLoaded(LivewireServiceProvider::class)) {
        $this->app->register(LivewireServiceProvider::class);
    }

    $profileOwner = User::factory()->create([
        'name' => 'Direct Livewire Member',
        'username' => 'direct_livewire_member',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSee('Direct Livewire Member')
        ->assertSee('@direct_livewire_member');
});

it('stops after the active user lookup when the username is missing', function (): void {
    $queries = [];

    DB::listen(function ($event) use (&$queries): void {
        $queries[] = $event->sql;
    });

    $this->get('/@missing_livewire_member')->assertNotFound();

    $profileQueries = collect($queries)
        ->filter(fn (string $query): bool => str_contains($query, 'from "users"')
            || str_contains($query, 'from "blocks"')
            || str_contains($query, 'from "follows"')
            || str_contains($query, 'from "media"'))
        ->values();

    $usernameLookups = $profileQueries
        ->filter(fn (string $query): bool => str_contains($query, 'from "users"') && str_contains($query, '"username" = ?'))
        ->values();

    expect($usernameLookups)->toHaveCount(1)
        ->and($profileQueries->implode("\n"))->not->toContain('from "blocks"')
        ->and($profileQueries->implode("\n"))->not->toContain('from "follows"')
        ->and($profileQueries->implode("\n"))->not->toContain('from "media"');
});

it('checks block relationships before private profile rendering', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'blocked_private_owner',
        'is_private' => true,
        'profile_visibility' => 'followers_only',
    ]);
    $viewer = User::factory()->create();

    $profileOwner->block($viewer);

    $this->actingAs($viewer)
        ->get('/@blocked_private_owner')
        ->assertNotFound();
});

it('renders private state from mount for non-followers', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'mount_private_owner',
        'is_private' => true,
        'profile_visibility' => 'followers_only',
    ]);

    Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('showPrivateProfile', true)
        ->assertSet('profileVisibility', 'followers_only')
        ->assertSee('This account is private')
        ->assertDontSee('data-ui="profile-shell"', false);
});

it('loads header data and defaults the active tab to posts for visible profiles', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'header_loaded_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $follower = User::factory()->create();

    $follower->follow($profileOwner);
    Pet::factory()->count(2)->for($profileOwner)->create();

    $component = Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('activeTab', 'posts')
        ->assertSet('showPrivateProfile', false);

    $resolvedOwner = $component->instance()->profileOwner;

    expect($resolvedOwner)->toBeInstanceOf(User::class)
        ->and($resolvedOwner->relationLoaded('media'))->toBeTrue()
        ->and((int) $resolvedOwner->followers_count)->toBe(1)
        ->and((int) $resolvedOwner->pets_count)->toBe(2);
});
