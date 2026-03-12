<?php

use App\Enums\PostStatus;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use App\Services\SyncGroupCountersService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('posts count sync includes published group posts and shared posts', function (): void {
    $owner = User::factory()->create();
    $group = Group::factory()->create([
        'owner_user_id' => $owner->getKey(),
        'owner_id' => $owner->getKey(),
        'posts_count' => 0,
    ]);

    Post::factory()->create([
        'group_id' => $group->getKey(),
        'status' => PostStatus::Published->value,
    ]);

    Post::factory()->create([
        'group_id' => $group->getKey(),
        'status' => PostStatus::Draft->value,
        'published_at' => null,
    ]);

    $shared = Post::factory()->create([
        'status' => PostStatus::Published->value,
    ]);

    $group->attachSharedPost($shared, (int) $owner->getKey());

    app(SyncGroupCountersService::class)->syncPostsCount($group);

    $group->refresh();
    expect((int) $group->posts_count)->toBe(2);
});
