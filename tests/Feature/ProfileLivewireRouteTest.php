<?php

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\PostMedia;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\CounterCacheService;
use App\Services\ReactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
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

it('mounts the lazy photos tab component and fetches visible post photos independently', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'child_photo_tab_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $post = Post::factory()->for($profileOwner)->create([
        'body' => 'Nested photo tab post',
        'body_html' => '<p>Nested photo tab post</p>',
        'type' => Post::TYPE_PHOTO,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    PostMedia::factory()->for($post, 'post')->create([
        'file_path' => 'posts/nested-photo-tab.jpg',
        'media_type' => 'image',
    ]);

    Livewire::test('profile.tabs.photos', ['profileUserId' => $profileOwner->getKey()])
        ->assertSee('nested-photo-tab.jpg')
        ->assertSee('data-ui="profile-photos-grid"', false);
});

it('mounts the lazy about tab component and presents public biographical sections', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-23 12:00:00'));

    try {
        $profileOwner = User::factory()->create([
            'name' => 'Biographical Owner',
            'display_name' => 'Bio Crew',
            'username' => 'bio_section_owner',
            'email' => 'bio-owner@example.test',
            'headline' => 'Neighborhood rescue coordinator',
            'pronouns' => 'they/them',
            'bio' => "I help senior pets find patient homes.\nEvery weekend, I coordinate foster visits and transport.",
            'location' => 'Vilnius',
            'website' => 'https://bio.example/about',
            'social_links' => [
                'instagram' => 'https://instagram.com/bio_pets',
            ],
            'interests_text' => 'rescue, senior pets, training',
            'birth_date' => '1998-05-20',
            'last_seen_at' => Carbon::parse('2026-05-22 12:00:00'),
            'created_at' => Carbon::parse('2024-04-14 12:00:00'),
            'posts_count' => 12,
            'pets_count' => 3,
            'photos_count' => 8,
            'post_reactions_received_count' => 34,
            'post_comments_received_count' => 9,
            'last_post_created_at' => Carbon::parse('2026-05-21 10:00:00'),
            'privacy_display_email' => true,
            'privacy_display_location' => true,
            'privacy_display_birthdate' => true,
            'privacy_display_last_seen' => true,
            'is_private' => false,
            'profile_visibility' => 'public',
        ]);

        Livewire::test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
            ->assertSee('data-ui="profile-tab-panel"', false)
            ->assertSee('data-ui="profile-about-bio"', false)
            ->assertSee('I help senior pets find patient homes.')
            ->assertSee('Every weekend, I coordinate foster visits and transport.')
            ->assertDontSee('line-clamp', false)
            ->assertSee('data-ui="profile-about-bio-details"', false)
            ->assertSee('Member since April 2024.')
            ->assertSee('data-icon="calendar"', false)
            ->assertSee('data-icon="map-pin"', false)
            ->assertSee('Vilnius')
            ->assertSee('data-icon="external-link"', false)
            ->assertSee('href="https://bio.example/about"', false)
            ->assertSee('bio.example/about')
            ->assertSee('data-icon="cake"', false)
            ->assertSee('Age 28')
            ->assertDontSee('May 20, 1998')
            ->assertSee('data-ui="profile-about-activity-summary"', false)
            ->assertSee('grid grid-cols-2 gap-3', false)
            ->assertSee('Activity summary')
            ->assertSee('Posts created')
            ->assertSee('12')
            ->assertSee('Reactions received')
            ->assertSee('34')
            ->assertSee('Comments received')
            ->assertSee('9')
            ->assertSee('Most recent post')
            ->assertSee('May 21, 2026')
            ->assertDontSee('data-ui="profile-about-activity-chart"', false)
            ->assertSee('data-ui="profile-about-overview"', false)
            ->assertSee('Profile basics')
            ->assertSee('About Bio Crew')
            ->assertSee('Neighborhood rescue coordinator')
            ->assertSee('they/them')
            ->assertSee('data-ui="profile-about-interests"', false)
            ->assertSee('rescue')
            ->assertSee('senior pets')
            ->assertSee('training')
            ->assertSee('data-ui="profile-about-contact"', false)
            ->assertSee('Instagram')
            ->assertSee('instagram.com/bio_pets')
            ->assertDontSee('mailto:bio-owner@example.test', false);
    } finally {
        Carbon::setTestNow();
    }
});

it('renders the about activity summary from precomputed user columns without live aggregate queries', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-23 12:00:00'));

    try {
        $profileOwner = User::factory()->create([
            'name' => 'Activity Owner',
            'username' => 'activity_summary_owner',
            'bio' => 'Activity summary reads cached values.',
            'is_private' => false,
            'profile_visibility' => 'public',
        ]);
        $reactor = User::factory()->create();
        $commenter = User::factory()->create();

        $post = Post::factory()->for($profileOwner)->create([
            'created_at' => Carbon::parse('2026-05-20 09:00:00'),
            'updated_at' => Carbon::parse('2026-05-20 09:00:00'),
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        Comment::factory()
            ->for($post)
            ->for($commenter, 'user')
            ->create();

        app(ReactionService::class)->react($reactor, $post->fresh(), 'love');

        $profileOwner->refresh();

        expect((int) $profileOwner->posts_count)->toBe(1)
            ->and((int) $profileOwner->post_comments_received_count)->toBe(1)
            ->and((int) $profileOwner->post_reactions_received_count)->toBe(1)
            ->and($profileOwner->last_post_created_at?->toDateTimeString())->toBe('2026-05-20 09:00:00');

        $profileOwner->updateQuietly([
            'posts_count' => 0,
            'post_comments_received_count' => 0,
            'post_reactions_received_count' => 0,
            'last_post_created_at' => null,
        ]);

        app(CounterCacheService::class)->rebuildProfileActivitySummary();
        $profileOwner->refresh();

        expect((int) $profileOwner->posts_count)->toBe(1)
            ->and((int) $profileOwner->post_comments_received_count)->toBe(1)
            ->and((int) $profileOwner->post_reactions_received_count)->toBe(1)
            ->and($profileOwner->last_post_created_at?->toDateTimeString())->toBe('2026-05-20 09:00:00');

        DB::enableQueryLog();

        Livewire::test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
            ->assertSee('data-ui="profile-about-activity-summary"', false)
            ->assertSee('Posts created')
            ->assertSee('1')
            ->assertSee('Reactions received')
            ->assertSee('Comments received')
            ->assertSee('May 20, 2026');

        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->map(fn (string $query): string => strtolower($query))
            ->implode("\n");

        DB::disableQueryLog();

        expect($queries)
            ->not->toContain('from "posts"')
            ->not->toContain('count(*)')
            ->not->toContain('sum(')
            ->not->toContain('max(');
    } finally {
        DB::disableQueryLog();
        Carbon::setTestNow();
    }
});

it('renders a compact about pet summary from visible pets without per-pet queries', function (): void {
    Storage::fake('public');

    $profileOwner = User::factory()->create([
        'name' => 'Pet Summary Owner',
        'display_name' => 'Pet Summary Crew',
        'username' => 'pet_summary_owner',
        'bio' => 'Pet summary should preview visible pets.',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $visiblePet = Pet::factory()->for($profileOwner)->create([
        'name' => 'Pickles',
        'is_public' => true,
        'created_at' => Carbon::parse('2026-05-22 10:00:00'),
    ]);
    $olderVisiblePet = Pet::factory()->for($profileOwner)->create([
        'name' => 'Mochi',
        'is_public' => true,
        'created_at' => Carbon::parse('2026-05-20 10:00:00'),
    ]);
    $privatePet = Pet::factory()->for($profileOwner)->create([
        'name' => 'Hidden Whiskers',
        'is_public' => false,
        'created_at' => Carbon::parse('2026-05-23 10:00:00'),
    ]);

    $visiblePet->addMedia(UploadedFile::fake()->image('pickles.jpg', 160, 160))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);
    $olderVisiblePet->addMedia(UploadedFile::fake()->image('mochi.jpg', 160, 160))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);

    DB::enableQueryLog();

    Livewire::test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
        ->assertSee('data-ui="profile-about-activity-summary"', false)
        ->assertSee('data-ui="profile-about-pet-summary"', false)
        ->assertSee('Pets at a glance')
        ->assertSee('href="'.route('pets.show', ['pet' => $visiblePet->slug]).'"', false)
        ->assertSee('href="'.route('pets.show', ['pet' => $olderVisiblePet->slug]).'"', false)
        ->assertSee('Pickles')
        ->assertSee('Pickles profile photo')
        ->assertSee('Mochi')
        ->assertSee('Mochi profile photo')
        ->assertDontSee('Hidden Whiskers');

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->map(fn (string $query): string => strtolower($query));

    DB::disableQueryLog();

    expect($queries->filter(fn (string $query): bool => str_contains($query, 'from "pets"'))->count())->toBe(1)
        ->and($queries->filter(fn (string $query): bool => str_contains($query, 'from "media"'))->count())->toBe(1)
        ->and($queries->implode("\n"))
        ->not->toContain('from "species"')
        ->not->toContain('from "breeds"')
        ->not->toContain('from "pet_tags"');
});

it('shows private pets in the about pet summary to the profile owner', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Private Pet Owner',
        'username' => 'private_pet_summary_owner',
        'bio' => 'Owner can see all pets in their summary.',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Pet::factory()->for($profileOwner)->create([
        'name' => 'Private Pancake',
        'is_public' => false,
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
        ->assertSee('data-ui="profile-about-pet-summary"', false)
        ->assertSee('Private Pancake');
});

it('keeps privacy-gated about fields hidden from visitors and never reveals the birth date', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-23 12:00:00'));

    try {
        $profileOwner = User::factory()->create([
            'name' => 'Private Details Owner',
            'username' => 'private_about_fields',
            'email' => 'private-about@example.test',
            'bio' => 'Public bio remains visible.',
            'location' => 'Hidden City',
            'website' => null,
            'social_links' => null,
            'interests_text' => null,
            'birth_date' => '1994-05-20',
            'last_seen_at' => Carbon::parse('2026-05-22 12:00:00'),
            'privacy_display_email' => false,
            'privacy_display_location' => false,
            'privacy_display_birthdate' => false,
            'privacy_display_last_seen' => false,
            'is_private' => false,
            'profile_visibility' => 'public',
        ]);

        Livewire::test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
            ->assertSee('Public bio remains visible.')
            ->assertSee('Member since')
            ->assertDontSee('Hidden City')
            ->assertDontSee('May 20, 1994')
            ->assertDontSee('Age 32')
            ->assertDontSee('private-about@example.test')
            ->assertDontSee('mailto:private-about@example.test', false);

        Livewire::actingAs($profileOwner)
            ->test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
            ->assertSee('Hidden City')
            ->assertDontSee('May 20, 1994')
            ->assertDontSee('Age 32')
            ->assertDontSee('private-about@example.test')
            ->assertDontSee('mailto:private-about@example.test', false);
    } finally {
        Carbon::setTestNow();
    }
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
