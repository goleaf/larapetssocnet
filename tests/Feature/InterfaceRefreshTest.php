<?php

use App\Models\Activities\Event;
use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders a useful authenticated dashboard instead of the starter panel', function (): void {
    $user = User::factory()->create([
        'name' => 'Mira Stone',
        'username' => 'mira_stone',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Hi Mira, keep your pet world moving.')
        ->assertSee('Share an update')
        ->assertSee('Find groups')
        ->assertSee('Community paths')
        ->assertDontSee("You're logged in!");
});

it('renders larger touch targets and accessible mobile navigation markers', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('aria-label="Mobile navigation"', false)
        ->assertSee('aria-controls="mobile-primary-navigation"', false)
        ->assertSee('min-h-11', false);
});

it('renders the desktop app left rail as an independent viewport scroll region', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('explore.index'))
        ->assertSuccessful()
        ->assertSee('data-ui="app-left-rail"', false)
        ->assertSee('max-h-[calc(100dvh-7rem)]', false)
        ->assertSee('overflow-y-auto', false)
        ->assertSee('overscroll-contain', false);
});

it('renders unified feed filters and semantic feed markup', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('feed.index'))
        ->assertSuccessful()
        ->assertSee('data-feed-surface="warm-editorial"', false)
        ->assertSee('Feed sources')
        ->assertSee('All types')
        ->assertDontSee('Feed style')
        ->assertDontSee('High Contrast')
        ->assertSee('<ul role="feed"', false)
        ->assertSee('role="status"', false);
});

it('renders refined post cards with article semantics and stateful actions', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create([
        'name' => 'Riley Hart',
        'username' => 'riley_hart',
        'is_private' => false,
        'is_banned' => false,
    ]);

    Post::factory()->for($author)->create([
        'body' => 'A better card surface for the community feed.',
        'body_html' => '<p>A better card surface for the community feed.</p>',
        'status' => 'published',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->get(route('explore.index'))
        ->assertSuccessful()
        ->assertSee('role="feed"', false)
        ->assertSee('aria-label="Explore public posts"', false)
        ->assertSee('data-ui="post-card"', false)
        ->assertSee('<article', false)
        ->assertSee('aria-label="Like post by Riley Hart"', false)
        ->assertSee('x-bind:aria-pressed="liked"', false)
        ->assertSee('x-bind:aria-busy="likeBusy"', false)
        ->assertSee('min-h-11', false);
});

it('renders marketplace and group cards as accessible entry points', function (): void {
    $seller = User::factory()->create(['name' => 'Casey Seller']);
    $viewer = User::factory()->create();

    MarketplaceListing::factory()->create([
        'user_id' => $seller->getKey(),
        'title' => 'Portable pet carrier',
        'description' => 'Clean carrier with a soft liner and extra side pockets.',
        'listing_type' => 'sale',
        'status' => MarketplaceListing::STATUS_ACTIVE,
        'location_text' => 'Vilnius',
        'views_count' => 42,
    ]);

    Group::factory()->public()->create([
        'name' => 'City Pet Walkers',
        'species_focus' => 'all',
        'members_count' => 12,
        'posts_count' => 5,
    ]);

    $this->actingAs($viewer)
        ->get(route('marketplace.index'))
        ->assertSuccessful()
        ->assertSee('data-ui="listing-card"', false)
        ->assertSee('aria-label="Marketplace listing: Portable pet carrier"', false)
        ->assertSee('View details')
        ->assertSee('Casey Seller');

    $this->actingAs($viewer)
        ->get(route('groups.index'))
        ->assertSuccessful()
        ->assertSee('data-ui="group-card"', false)
        ->assertSee('aria-label="Group: City Pet Walkers"', false)
        ->assertSee('All')
        ->assertSee('min-h-11', false);
});

it('renders global search results as actionable cards', function (): void {
    User::factory()->create([
        'name' => 'Taylor Searchable',
        'username' => 'taylor_searchable',
        'headline' => 'Pet adoption volunteer',
        'is_private' => false,
        'show_in_explore' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('search.index', [
            'q' => 'Taylor',
            'type' => 'users',
        ]))
        ->assertSuccessful()
        ->assertSee('aria-label="Search results"', false)
        ->assertSee('data-ui="search-result-card"', false)
        ->assertSee('Taylor Searchable')
        ->assertSee('Open')
        ->assertSee('min-h-11', false);
});

it('renders pet browse and event cards as richer accessible entry points', function (): void {
    $owner = User::factory()->create(['name' => 'Jordan Keeper']);

    Pet::factory()->for($owner, 'owner')->create([
        'name' => 'Maple',
        'species' => 'dog',
        'breed' => 'Collie',
        'sex' => 'female',
        'is_public' => true,
        'is_adoptable' => true,
        'adoption_status' => 'available',
    ]);

    Event::factory()->create([
        'creator_user_id' => $owner->getKey(),
        'title' => 'Weekend Pet Walk',
        'description' => 'A relaxed community walk for nearby pet families.',
        'status' => 'scheduled',
        'start_at' => now()->addWeek(),
        'location_text' => 'Riverside Park',
        'attendees_count' => 9,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('pets.explore'))
        ->assertSuccessful()
        ->assertSee('data-ui="pet-card"', false)
        ->assertSee('aria-label="View profile for Maple"', false)
        ->assertSee('View Profile')
        ->assertSee('Jordan Keeper');

    $this->actingAs(User::factory()->create())
        ->get(route('pets.adopt'))
        ->assertSuccessful()
        ->assertSee('data-ui="pet-card"', false)
        ->assertSee('Maple')
        ->assertSee('No adoption fee');

    $this->actingAs(User::factory()->create())
        ->get(route('events.index'))
        ->assertSuccessful()
        ->assertSee('data-ui="event-card"', false)
        ->assertSee('aria-label="Event: Weekend Pet Walk"', false)
        ->assertSee('Riverside Park')
        ->assertSee('min-h-11', false);
});

it('renders guest auth pages with clearer headers and touch-sized actions', function (): void {
    User::factory()->create([
        'email' => 'demo@example.com',
        'username' => 'demo_user',
    ]);

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('data-ui="guest-shell"', false)
        ->assertSee('data-ui="guest-auth-panel"', false)
        ->assertSee('data-ui="login-form"', false)
        ->assertSee('Log in to your pet community')
        ->assertSee('data-ui="inline-password-reset-form"', false)
        ->assertDontSee('data-ui="quick-login-panel"', false)
        ->assertSee('min-h-11', false);

    $this->get(route('register'))
        ->assertSuccessful()
        ->assertSee('data-ui="register-form"', false)
        ->assertSee('Start your pet profile network')
        ->assertSee('sm:grid-cols-2', false)
        ->assertSee('min-h-11', false);

    $this->get(route('password.request'))
        ->assertSuccessful()
        ->assertSee('data-ui="password-email-form"', false)
        ->assertSee('Reset access to your account')
        ->assertSee('Back to login')
        ->assertSee('min-h-11', false);
});

it('renders password and email verification pages with consistent secure panels', function (): void {
    $user = User::factory()->unverified()->create();

    $this->get(route('password.reset', ['token' => 'interface-token']))
        ->assertSuccessful()
        ->assertSee('data-ui="password-reset-form"', false)
        ->assertSee('Create a new password')
        ->assertSee('Back to login')
        ->assertSee('min-h-11', false);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertSuccessful()
        ->assertSee('data-ui="email-verification-panel"', false)
        ->assertSee('Check your inbox')
        ->assertSee('Resend email')
        ->assertSee('min-h-11', false);

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertSuccessful()
        ->assertSee('data-ui="password-confirm-form"', false)
        ->assertSee('Confirm your password')
        ->assertSee('min-h-11', false);
});
