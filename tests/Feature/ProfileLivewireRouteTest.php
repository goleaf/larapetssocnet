<?php

use App\Models\Content\Post;
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
    $profileOwner->forceFill(['pets_count' => 2])->saveQuietly();

    $component = Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('activeTab', 'posts')
        ->assertSet('showPrivateProfile', false);

    $resolvedOwner = $component->instance()->profileOwner;

    expect($resolvedOwner)->toBeInstanceOf(User::class)
        ->and($resolvedOwner->relationLoaded('media'))->toBeTrue()
        ->and((int) $resolvedOwner->followers_count)->toBe(1)
        ->and((int) $resolvedOwner->pets_count)->toBe(2);
});

it('activates the pets tab through the profile livewire action', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'stats_pet_tab_owner',
        'pets_count' => 1,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Pet::factory()->for($profileOwner)->create([
        'name' => 'Stats Tab Pet',
    ]);

    Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('activeTab', 'posts')
        ->call('activateTab', 'pets')
        ->assertSet('activeTab', 'pets')
        ->assertSee('Stats Tab Pet')
        ->assertSee('aria-current="page"', false);

    expect(session('profiles.'.$profileOwner->getKey().'.active_tab'))->toBe('pets');
});

it('refreshes the profile pets tab count after a nested pet create event', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'pet_count_refresh_owner',
        'pets_count' => 0,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $component = Livewire::actingAs($profileOwner)
        ->test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSee('Pets (0)');

    $pet = Pet::factory()->for($profileOwner)->create([
        'name' => 'Count Refresh Pet',
    ]);
    $profileOwner->forceFill(['pets_count' => 1])->saveQuietly();

    $component
        ->dispatch('profile-pet-created', petId: $pet->getKey())
        ->assertSee('Pets (1)');
});

it('restores the last profile tab from the browser session', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'session_tab_owner',
        'pets_count' => 1,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Pet::factory()->for($profileOwner)->create([
        'name' => 'Remembered Session Pet',
    ]);

    $this->withSession([
        'profiles.'.$profileOwner->getKey().'.active_tab' => 'pets',
    ]);

    Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('activeTab', 'pets')
        ->assertSee('Remembered Session Pet')
        ->assertSee('href="#pets"', false);
});

it('ignores an owner-only scheduled tab stored for a visitor session', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'visitor_scheduled_session_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->actingAs(User::factory()->create())
        ->withSession([
            'profiles.'.$profileOwner->getKey().'.active_tab' => 'scheduled',
        ]);

    Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('activeTab', 'posts')
        ->assertDontSee('Scheduled (', false);
});

it('renders hash based profile tab links for browser activation', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'hash_tabs_owner',
        'pets_count' => 1,
        'photos_count' => 2,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->get('/@hash_tabs_owner')
        ->assertOk()
        ->assertSee('x-data="profileTabs(', false)
        ->assertSee('href="#posts"', false)
        ->assertSee('href="#pets"', false)
        ->assertSee('href="#photos"', false)
        ->assertSee('href="#about"', false)
        ->assertDontSee('?tab=pets', false);
});

it('mounts the lazy pets tab component and fetches pets independently', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'child_pet_tab_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Pet::factory()->for($profileOwner)->create([
        'name' => 'Nested Component Pet',
    ]);

    Livewire::test('profile.tabs.pets', ['profileUserId' => $profileOwner->getKey()])
        ->assertSee('Nested Component Pet')
        ->assertSee('data-ui="profile-tab-panel"', false);
});

it('mounts the nested posts tab only after posts becomes the active profile tab', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'child_post_tab_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Pet::factory()->for($profileOwner)->create([
        'name' => 'Initially Active Pet Tab',
    ]);

    Post::factory()->for($profileOwner)->create([
        'body' => 'Nested Component Post Body',
        'body_html' => '<p>Nested Component Post Body</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->withSession([
        'profiles.'.$profileOwner->getKey().'.active_tab' => 'pets',
    ]);

    Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('activeTab', 'pets')
        ->assertSee('Initially Active Pet Tab')
        ->assertDontSee('Nested Component Post Body')
        ->assertDontSee('id="profile-panel-posts"', false)
        ->call('activateTab', 'posts')
        ->assertSet('activeTab', 'posts')
        ->assertSee('Nested Component Post Body')
        ->assertSee('id="profile-panel-posts"', false);
});

it('saves cover focal point through the profile livewire action for the owner', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'cover_action_owner',
        'cover_photo_position' => 50,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('pages.profile.show', ['user' => $profileOwner->username])
        ->call('saveCoverPosition', 78.456)
        ->assertReturned(78.46);

    expect((float) $profileOwner->refresh()->cover_photo_position)->toBe(78.46);
});

it('rejects cover focal point livewire saves from non-owners', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'cover_action_locked',
        'cover_photo_position' => 50,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages.profile.show', ['user' => $profileOwner->username])
        ->call('saveCoverPosition', 80)
        ->assertForbidden();

    expect((float) $profileOwner->refresh()->cover_photo_position)->toBe(50.0);
});

it('validates cover focal point values in the profile livewire action', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'cover_action_validation',
        'cover_photo_position' => 50,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('pages.profile.show', ['user' => $profileOwner->username])
        ->call('saveCoverPosition', 101)
        ->assertHasErrors(['position' => 'max']);

    expect((float) $profileOwner->refresh()->cover_photo_position)->toBe(50.0);
});
