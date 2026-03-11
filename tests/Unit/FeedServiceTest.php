<?php

use App\Models\Post;
use App\Models\User;
use App\Services\FeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

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
