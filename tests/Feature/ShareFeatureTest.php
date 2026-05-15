<?php

use App\Models\Content\Post;
use App\Models\Content\Share;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('tracks a share and increments shares_count', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.share', $post), ['method' => 'copy_link'])
        ->assertOk()
        ->assertJsonPath('shares_count', 1);

    $this->assertDatabaseHas('shares', [
        'user_id' => $user->id,
        'shareable_type' => (new Post)->getMorphClass(),
        'shareable_id' => $post->id,
    ]);

    expect($post->refresh()->shares_count)->toBe(1);
});

it('does not double count shares by the same user', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.share', $post), ['method' => 'copy_link'])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('posts.share', $post), ['method' => 'copy_link'])
        ->assertOk()
        ->assertJsonPath('shares_count', 1);

    expect(Share::query()->count())->toBe(1);
    expect($post->refresh()->shares_count)->toBe(1);
});

it('prevents sharing posts the viewer cannot access', function (): void {
    $author = User::factory()->create(['is_private' => true]);
    $viewer = User::factory()->create();

    $post = Post::factory()->for($author)->create([
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($viewer)
        ->postJson(route('posts.share', $post), ['method' => 'copy_link'])
        ->assertForbidden();
});
