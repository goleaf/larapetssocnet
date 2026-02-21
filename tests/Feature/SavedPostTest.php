<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('saves and unsaves a post by toggling saved state', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => 'public',
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.save.toggle', $post))
        ->assertOk()
        ->assertJsonPath('saved', true);

    $this->assertDatabaseHas('saved_posts', [
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('posts.save.toggle', $post))
        ->assertOk()
        ->assertJsonPath('saved', false);

    $this->assertDatabaseMissing('saved_posts', [
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);
});
