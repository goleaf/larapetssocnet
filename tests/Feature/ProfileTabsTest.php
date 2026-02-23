<?php

use App\Models\Badge;
use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('groups tab shows user groups with role indicators', function (): void {
    $user = User::factory()->create(['username' => 'group_user']);
    $group = Group::factory()->create(['name' => 'Pet Lovers Club']);
    $user->groups()->attach($group->id, [
        'role' => 'owner',
        'status' => 'approved',
        'joined_at' => now(),
    ]);

    $this->get(route('profile.show', ['user' => $user, 'tab' => 'groups']))
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

    $this->get(route('profile.show', ['user' => $user, 'tab' => 'events']))
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

    $this->get(route('profile.show', ['user' => $user, 'tab' => 'contests']))
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

    $this->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertSee('First Post');
});

test('mutual connections shown to visitor who shares follows', function (): void {
    $alice = User::factory()->create(['username' => 'alice_mutual']);
    $bob = User::factory()->create(['username' => 'bob_mutual']);
    $shared = User::factory()->create(['username' => 'shared_friend', 'name' => 'Shared Friend']);

    $shared->follow($alice);
    $shared->follow($bob);

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
