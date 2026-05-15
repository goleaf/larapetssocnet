<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\VisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('canView handles guest and follower visibility rules', function (): void {
    $service = app(VisibilityService::class);
    $author = User::factory()->create(['is_private' => false]);
    $follower = User::factory()->create();
    $stranger = User::factory()->create();

    $follower->follow($author);
    $author->approveFollowRequest($follower);

    $public = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_PUBLIC]);
    $followers = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_FOLLOWERS]);
    $private = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_PRIVATE]);

    expect($service->canView(null, $public))->toBeTrue();
    expect($service->canView(null, $followers))->toBeFalse();
    expect($service->canView(null, $private))->toBeFalse();
    expect($service->canView($follower, $followers))->toBeTrue();
    expect($service->canView($stranger, $followers))->toBeFalse();
    expect($service->canView($author, $private))->toBeTrue();
});

it('canView returns true for admin regardless of visibility', function (): void {
    Role::findOrCreate('admin');

    $service = app(VisibilityService::class);
    $author = User::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $private = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_PRIVATE]);

    expect($service->canView($admin, $private))->toBeTrue();
});

it('canView returns false when blocked in either direction', function (): void {
    $service = app(VisibilityService::class);
    $author = User::factory()->create(['is_private' => false]);
    $viewer = User::factory()->create();
    $post = Post::factory()->for($author)->create(['visibility' => Post::VISIBILITY_PUBLIC]);

    $viewer->block($author);
    expect($service->canView($viewer, $post))->toBeFalse();
});

it('shouldWarnOnDowngrade is based on engagement and restriction direction', function (): void {
    $service = app(VisibilityService::class);
    $post = Post::factory()->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
        'likes_count' => 2,
        'comments_count' => 1,
    ]);

    expect($service->shouldWarnOnDowngrade($post, Post::VISIBILITY_FOLLOWERS))->toBeTrue();
    expect($service->shouldWarnOnDowngrade($post, Post::VISIBILITY_PRIVATE))->toBeTrue();
    expect($service->shouldWarnOnDowngrade($post, Post::VISIBILITY_PUBLIC))->toBeFalse();

    $post->update(['likes_count' => 0, 'comments_count' => 0]);
    expect($service->shouldWarnOnDowngrade($post, Post::VISIBILITY_PRIVATE))->toBeFalse();
});

it('returns visibility labels and icons', function (): void {
    $service = app(VisibilityService::class);

    expect($service->getVisibilityLabel(Post::VISIBILITY_PUBLIC))->toBe('Public');
    expect($service->getVisibilityLabel(Post::VISIBILITY_FOLLOWERS))->toBe('Followers');
    expect($service->getVisibilityLabel(Post::VISIBILITY_PRIVATE))->toBe('Only me');

    expect($service->getVisibilityIcon(Post::VISIBILITY_PUBLIC))->toBe('🌍');
    expect($service->getVisibilityIcon(Post::VISIBILITY_FOLLOWERS))->toBe('👥');
    expect($service->getVisibilityIcon(Post::VISIBILITY_PRIVATE))->toBe('🔒');
});
