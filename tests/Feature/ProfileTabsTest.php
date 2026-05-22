<?php

use App\Enums\PostStatus;
use App\Models\Activities\Contest;
use App\Models\Activities\ContestEntry;
use App\Models\Activities\Event;
use App\Models\Content\Post;
use App\Models\Gamification\Badge;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Models\Social\Follow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

if (! function_exists('profileTabsMarkup')) {
    function profileTabsMarkup(string $html): string
    {
        $start = strpos($html, 'data-ui="tabs"');

        if ($start === false) {
            return '';
        }

        $end = strpos($html, '</nav>', $start);

        return substr($html, $start, $end === false ? null : $end - $start);
    }
}

test('profile tab navigation shows requested tabs with counter cached labels', function (): void {
    $user = User::factory()->create([
        'username' => 'cached_tab_counts',
        'posts_count' => 47,
        'pets_count' => 3,
        'photos_count' => 128,
        'scheduled_posts_count' => 4,
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $user]))
        ->assertOk();

    $tabs = profileTabsMarkup($response->getContent());

    expect($tabs)->toContain('Posts (47)')
        ->and($tabs)->toContain('Pets (3)')
        ->and($tabs)->toContain('Photos (128)')
        ->and($tabs)->toContain('About')
        ->and($tabs)->not->toContain('Scheduled')
        ->and($tabs)->not->toContain('Groups')
        ->and($tabs)->not->toContain('Events')
        ->and($tabs)->not->toContain('Contests')
        ->and($tabs)->not->toContain('Followers')
        ->and($tabs)->not->toContain('Following')
        ->and($tabs)->not->toContain('Likes');
});

test('scheduled tab is visible only to the profile owner', function (): void {
    $owner = User::factory()->create([
        'username' => 'scheduled_owner',
        'scheduled_posts_count' => 0,
    ]);

    Post::factory()->for($owner)->create([
        'body' => 'scheduled-profile-owner-only-post',
        'status' => PostStatus::Scheduled->value,
        'published_at' => now()->addDay(),
    ]);

    $owner = $owner->fresh();

    $ownerResponse = $this->actingAs($owner)
        ->get(route('profile.show', ['user' => $owner, 'tab' => 'scheduled']))
        ->assertOk()
        ->assertSee('scheduled-profile-owner-only-post');

    expect(profileTabsMarkup($ownerResponse->getContent()))->toContain('Scheduled (1)');

    $visitorResponse = $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $owner, 'tab' => 'scheduled']))
        ->assertOk()
        ->assertDontSee('scheduled-profile-owner-only-post');

    expect(profileTabsMarkup($visitorResponse->getContent()))->not->toContain('Scheduled');
});

test('photo tab count respects profile visibility for the current viewer', function (): void {
    $owner = User::factory()->create([
        'username' => 'private_photo_counts',
        'profile_visibility' => 'followers_only',
        'is_private' => true,
        'photos_count' => 128,
    ]);
    $follower = User::factory()->create();

    Follow::query()->create([
        'follower_id' => $follower->getKey(),
        'following_id' => $owner->getKey(),
        'status' => 'accepted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $followerResponse = $this->actingAs($follower)
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk();

    expect(profileTabsMarkup($followerResponse->getContent()))->toContain('Photos (128)');

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $owner]))
        ->assertOk()
        ->assertDontSee('Photos (128)');
});

test('scheduled post counter cache tracks post status transitions', function (): void {
    $owner = User::factory()->create([
        'username' => 'scheduled_counter_owner',
        'scheduled_posts_count' => 0,
    ]);
    $post = Post::factory()->for($owner)->create([
        'status' => PostStatus::Draft->value,
        'published_at' => null,
    ]);

    expect($owner->fresh()->scheduled_posts_count)->toBe(0);

    $post->update([
        'status' => PostStatus::Scheduled->value,
        'published_at' => now()->addDay(),
    ]);

    expect($owner->fresh()->scheduled_posts_count)->toBe(1);

    $post->update([
        'status' => PostStatus::Published->value,
        'published_at' => now(),
    ]);

    expect($owner->fresh()->scheduled_posts_count)->toBe(0);

    $post->update([
        'status' => PostStatus::Scheduled->value,
        'published_at' => now()->addDay(),
    ]);
    $post->delete();

    expect($owner->fresh()->scheduled_posts_count)->toBe(0);
});

test('groups tab shows user groups with role indicators', function (): void {
    $user = User::factory()->create(['username' => 'group_user']);
    $group = Group::factory()->create(['name' => 'Pet Lovers Club']);
    $user->groups()->attach($group->id, [
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $user, 'tab' => 'groups']))
        ->assertOk()
        ->assertSee('Pet Lovers Club')
        ->assertSee('👑');
});

test('events tab shows upcoming events', function (): void {
    $user = User::factory()->create(['username' => 'event_user']);
    $event = Event::factory()->create([
        'title' => 'Dog Walk Meetup',
        'start_at' => now()->addWeek(),
    ]);
    $event->respond($user, Event::ATTENDEE_GOING);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $user, 'tab' => 'events']))
        ->assertOk()
        ->assertSee('Dog Walk Meetup')
        ->assertSee('Upcoming Events');
});

test('contests tab shows entered contests', function (): void {
    $user = User::factory()->create(['username' => 'contest_user']);
    $contest = Contest::query()->create([
        'organizer_user_id' => User::factory()->create()->id,
        'title' => 'Cutest Pet 2024',
        'slug' => 'cutest-pet-2024',
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addWeek(),
    ]);
    ContestEntry::query()->create([
        'user_id' => $user->id,
        'contest_id' => $contest->id,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $user, 'tab' => 'contests']))
        ->assertOk()
        ->assertSee('Cutest Pet 2024');
});

test('badge strip renders earned badges on profile', function (): void {
    $user = User::factory()->create(['username' => 'badge_user']);
    $badge = Badge::query()->create([
        'name' => 'First Post',
        'slug' => 'first_post',
        'condition_type' => 'manual',
    ]);
    $user->badges()->attach($badge->id, ['awarded_at' => now()]);

    $this->actingAs(User::factory()->create())
        ->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertSee('First Post');
});

test('mutual connections shown to visitor who shares follows', function (): void {
    $alice = User::factory()->create(['username' => 'alice_mutual']);
    $bob = User::factory()->create(['username' => 'bob_mutual']);
    $shared = User::factory()->create(['username' => 'shared_friend', 'name' => 'Shared Friend']);
    $profileOnly = User::factory()->create(['username' => 'profile_only_friend']);
    $viewerOnly = User::factory()->create(['username' => 'viewer_only_friend']);
    $blockedShared = User::factory()->create(['username' => 'blocked_shared_friend']);

    $shared->follow($alice);
    $shared->follow($bob);
    $profileOnly->follow($bob);
    $viewerOnly->follow($alice);
    $blockedShared->follow($alice);
    $blockedShared->follow($bob);
    $blockedShared->blocking()->attach($alice->getKey());

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $mutualConnections = $alice->getMutualFollowers($bob);
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    expect($mutualConnections->pluck('username')->all())->toBe(['shared_friend'])
        ->and(strtolower((string) $queries[0]['query']))
        ->toContain('join "follows" as "viewer_followers"')
        ->toContain('join "follows" as "profile_followers"');

    $this->actingAs($alice)
        ->get(route('profile.show', ['user' => $bob]))
        ->assertOk()
        ->assertSee('People You Both Follow');
});

test('mutual connections hidden on own profile', function (): void {
    $user = User::factory()->create(['username' => 'self_viewer']);

    $this->actingAs($user)
        ->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertDontSee('People You Both Follow');
});
