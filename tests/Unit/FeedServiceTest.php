<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\FeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('getFeed returns paginated posts and state collections', function (): void {
    $user = User::factory()->create();

    Post::factory()->count(3)->create([
        'user_id' => $user->id,
        'visibility' => 'public',
    ]);

    $service = app(FeedService::class);
    $result = $service->getFeed($user, null, 15);

    expect($result)->toHaveKeys(['posts', 'myReactions', 'mySaved']);
    expect($result['posts']->count())->toBe(3);
    expect($result['posts']->first()?->toArray())->not->toHaveKey('current_user_reaction');
});

it('returns upcoming pet birthdays for followed users', function (): void {
    Cache::forget('feed:trending-hashtags');

    $viewer = User::factory()->create();
    $followedOwner = User::factory()->create();
    $unfollowedOwner = User::factory()->create();

    $viewer->following()->attach($followedOwner->getKey(), ['status' => 'accepted']);

    $birthdayPet = Pet::factory()->for($followedOwner)->create([
        'name' => 'Birthday Miso',
        'date_of_birth' => now()->addDays(3)->subYears(4)->toDateString(),
        'is_public' => true,
        'visibility' => 'public',
    ]);

    Pet::factory()->for($unfollowedOwner)->create([
        'name' => 'Hidden Birthday',
        'date_of_birth' => now()->addDays(3)->subYears(4)->toDateString(),
        'is_public' => true,
        'visibility' => 'public',
    ]);

    $sidebarData = app(FeedService::class)->getSidebarData($viewer);

    expect($sidebarData['upcomingBirthdays'])
        ->pluck('id')
        ->toContain($birthdayPet->getKey());
    expect($sidebarData['upcomingBirthdays']->firstWhere('id', $birthdayPet->getKey())?->getAttribute('days_until_birthday'))
        ->toBe(3);
    expect($sidebarData['upcomingBirthdays'])
        ->pluck('name')
        ->not->toContain('Hidden Birthday');
});
