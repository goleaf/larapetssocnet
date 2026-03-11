<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('prevents non owners from editing and deleting posts', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $post = Post::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route('posts.edit', $post))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->patch(route('posts.update', $post), [
            'body' => 'should-not-update',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('posts.destroy', $post))
        ->assertForbidden();

    $this->assertDatabaseHas('posts', [
        'id' => $post->getKey(),
        'user_id' => $owner->getKey(),
    ]);
});
