<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates updates soft deletes and restores a post', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('posts.store'), [
            'body' => 'initial-post-body',
            'visibility' => Post::VISIBILITY_PUBLIC,
        ])
        ->assertRedirect();

    $post = Post::query()->latest('id')->firstOrFail();

    expect($post->body)->toBe('initial-post-body');

    $this->actingAs($user)
        ->patch(route('posts.update', $post), [
            'body' => 'updated-post-body',
            'visibility' => Post::VISIBILITY_FOLLOWERS,
        ])
        ->assertRedirect(route('posts.show', $post));

    $post->refresh();

    expect($post->body)->toBe('updated-post-body');
    expect($post->visibility)->toBe(Post::VISIBILITY_FOLLOWERS);

    $this->actingAs($user)
        ->delete(route('posts.destroy', $post))
        ->assertRedirect();

    $this->assertSoftDeleted('posts', [
        'id' => $post->getKey(),
    ]);

    $post = Post::query()->withTrashed()->findOrFail($post->getKey());
    $post->restore();

    $this->assertDatabaseHas('posts', [
        'id' => $post->getKey(),
        'deleted_at' => null,
    ]);
});

it('rejects public posts linked to private pets', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => false]);

    $this->actingAs($owner)
        ->from(route('posts.create'))
        ->post(route('posts.store'), [
            'body' => 'Post about my private pet',
            'visibility' => Post::VISIBILITY_PUBLIC,
            'pet_id' => $pet->id,
        ])
        ->assertSessionHasErrors(['visibility']);
});
