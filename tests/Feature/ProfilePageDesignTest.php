<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Social\Follow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

if (! function_exists('profileDesignFollowers')) {
    function profileDesignFollowers(User $owner, int $count): void
    {
        $now = now();
        $password = Hash::make('password');
        $prefix = 'power_follower_'.$owner->getKey().'_';

        foreach (array_chunk(range(1, $count), 250) as $chunk) {
            User::query()->insert(array_map(fn (int $index): array => [
                'name' => 'Power Follower '.$index,
                'username' => $prefix.$index,
                'email' => $prefix.$index.'@example.test',
                'email_verified_at' => $now,
                'password' => $password,
                'profile_visibility' => 'public',
                'is_private' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        User::query()
            ->where('username', 'like', $prefix.'%')
            ->pluck('id')
            ->chunk(250)
            ->each(function ($ids) use ($owner, $now): void {
                Follow::query()->insert($ids->map(fn (int $id): array => [
                    'follower_id' => $id,
                    'following_id' => $owner->getKey(),
                    'status' => 'accepted',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }
}

it('renders facebook-style profile sections and actions for public profiles', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Ava Carter',
        'display_name' => 'Ava and Luna',
        'username' => 'ava_carter01',
        'headline' => 'Neighborhood rescue volunteer',
        'bio' => 'Ava shares slow weekend walks, foster wins, and practical notes for anxious rescue dogs.',
        'location' => 'Portland',
        'website' => 'https://ava.example',
        'privacy_display_location' => true,
        'is_private' => false,
    ]);

    $viewer = User::factory()->create();
    $friend = User::factory()->create([
        'name' => 'Friend User',
        'username' => 'friend_user',
    ]);

    $profileOwner->follow($friend);

    Pet::factory()->for($profileOwner)->create([
        'name' => 'Luna',
        'species' => 'dog',
    ]);

    Post::factory()->for($profileOwner)->create([
        'body' => 'profile-post-visible',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-shell"', false)
        ->assertSee('data-ui="profile-header"', false)
        ->assertSee('aria-labelledby="profile-header-title"', false)
        ->assertSee('id="profile-header-title"', false)
        ->assertSee('data-ui="profile-hero"', false)
        ->assertSee('data-ui="profile-stats"', false)
        ->assertSee('data-ui="profile-stat-card"', false)
        ->assertSee('data-ui="profile-identity-panel"', false)
        ->assertSee('data-ui="profile-identity-chip"', false)
        ->assertSee('data-ui="profile-tabs"', false)
        ->assertSee('data-ui="tabs"', false)
        ->assertSee('Ava and Luna')
        ->assertSee('Neighborhood rescue volunteer')
        ->assertSee('Ava shares slow weekend walks')
        ->assertSee('Portland')
        ->assertSee('ava.example')
        ->assertSee('Intro')
        ->assertSee('Friends')
        ->assertSee('Posts')
        ->assertSee('About')
        ->assertSee('Pets')
        ->assertSee('Photos')
        ->assertSee('Followers')
        ->assertSee('Following')
        ->assertDontSee('Likes')
        ->assertSee('Follow')
        ->assertSee('Message')
        ->assertSee('min-h-11', false)
        ->assertSee('focus-visible:outline-paw', false)
        ->assertSee('Friend User')
        ->assertSee('profile-post-visible')
        ->assertDontSee('Who To Follow');
});

it('renders the profile header as the topmost full-width section in the main profile view', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Top Header Owner',
        'username' => 'top_header_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $response = $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-header"', false)
        ->assertSee('w-full min-w-0 overflow-hidden', false)
        ->assertDontSee('<livewire:profile-header', false)
        ->assertDontSee('wire:id="profile-header', false);

    $html = $response->getContent();

    expect(strpos($html, 'data-ui="profile-header"'))->toBeLessThan(strpos($html, 'data-ui="profile-completeness"') ?: PHP_INT_MAX)
        ->and(strpos($html, 'data-ui="profile-header"'))->toBeLessThan(strpos($html, 'data-ui="profile-tabs"'));
});

it('renders a clearer private profile lockup for authenticated visitors', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Private Profile',
        'username' => 'private_profile_design',
        'is_private' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="private-profile-shell"', false)
        ->assertSee('data-ui="private-profile-hero"', false)
        ->assertSee('data-profile-section="profile-header"', false)
        ->assertSee('aria-labelledby="private-profile-header-title"', false)
        ->assertSee('This account is private')
        ->assertSee('min-h-11', false);
});

it('renders an intentional empty state for brand new public profiles', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'New Member',
        'username' => 'new_member_state',
        'display_name' => null,
        'headline' => null,
        'bio' => null,
        'location' => null,
        'city' => null,
        'website' => null,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-new-state"', false)
        ->assertSee('New member')
        ->assertSee('0 pets')
        ->assertSee('0 posts')
        ->assertSee('No posts published yet.')
        ->assertDontSee('No bio added yet');
});

it('shows guests public profile information with clear join prompts', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Guest Visible Owner',
        'username' => 'guest_visible_owner',
        'bio' => 'Public bio for guests to understand who this member is.',
        'website' => 'https://guest-visible.example',
        'location' => 'Hidden City',
        'privacy_display_location' => false,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-guest-cta"', false)
        ->assertSee('Join PetSocial')
        ->assertSee('Log In')
        ->assertSee('Public bio for guests')
        ->assertSee('guest-visible.example')
        ->assertDontSee('Hidden City')
        ->assertDontSee('>Message</a>', false);
});

it('keeps high volume profiles readable with formatted counters', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Power User',
        'username' => 'power_profile',
        'bio' => 'A busy profile with lots of community activity.',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    profileDesignFollowers($profileOwner, 1001);
    Pet::factory()->count(24)->for($profileOwner)->create();
    Post::factory()->count(125)->for($profileOwner)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('1,001')
        ->assertSee('24 pets')
        ->assertSee('125 posts')
        ->assertSee('data-ui="profile-stat-card"', false)
        ->assertSee('data-ui="profile-identity-chip"', false);
});

it('shows followers-only profiles as locked to guests and open to approved followers', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Followers Only Owner',
        'username' => 'followers_only_design',
        'bio' => 'Follower-only profile bio.',
        'is_private' => true,
        'profile_visibility' => 'followers_only',
    ]);
    $follower = User::factory()->create();

    Post::factory()->for($profileOwner)->create([
        'body' => 'approved-follower-visible-post',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="private-profile-shell"', false)
        ->assertSee('followers-only')
        ->assertSee('Join PetSocial')
        ->assertDontSee('approved-follower-visible-post');

    $follower->follow($profileOwner);
    $profileOwner->approveFollowRequest($follower);

    $this->actingAs($follower)
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertOk()
        ->assertSee('data-ui="profile-shell"', false)
        ->assertSee('Follower-only profile bio.')
        ->assertSee('approved-follower-visible-post')
        ->assertDontSee('This account is private');
});

it('keeps blocked profiles completely inaccessible', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'blocked_profile_design',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $blockedViewer = User::factory()->create();

    $profileOwner->block($blockedViewer);

    $this->actingAs($blockedViewer)
        ->get(route('profile.show', ['user' => $profileOwner]))
        ->assertNotFound();
});
