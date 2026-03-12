<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Services\VisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('allows owner to view any post status', function (): void {
    $owner = User::factory()->create();
    $post = Post::factory()->for($owner)->create([
        'status' => PostStatus::Draft->value,
        'visibility' => Post::VISIBILITY_PRIVATE,
        'published_at' => null,
    ]);

    $service = app(VisibilityService::class);

    expect($service->canView($owner, $post))->toBeTrue();
});

it('denies viewers for scheduled posts before publish time', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $viewer->follow($owner);
    $owner->approveFollowRequest($viewer);

    $post = Post::factory()->for($owner)->create([
        'status' => PostStatus::Scheduled->value,
        'published_at' => now()->addHour(),
        'visibility' => Post::VISIBILITY_FOLLOWERS,
    ]);

    $service = app(VisibilityService::class);

    expect($service->canView($viewer, $post))->toBeFalse();
});

it('allows followers to view follower-only published posts', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $viewer->follow($owner);
    $owner->approveFollowRequest($viewer);

    $post = Post::factory()->for($owner)->create([
        'status' => PostStatus::Published->value,
        'published_at' => now()->subMinute(),
        'visibility' => Post::VISIBILITY_FOLLOWERS,
    ]);

    $service = app(VisibilityService::class);

    expect($service->canView($viewer, $post))->toBeTrue();
});
