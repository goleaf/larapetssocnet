<?php

use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows owners to attach and detach pets on posts', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['posts_count' => 0, 'is_public' => true]);
    $post = Post::factory()->for($owner)->create(['pet_id' => null, 'tagged_pets' => []]);

    $this->actingAs($owner)
        ->postJson(route('pets.posts.attach', ['pet' => $pet, 'post' => $post]))
        ->assertOk()
        ->assertJsonPath('pet_id', $pet->id);

    expect($post->fresh()->pet_id)->toBe($pet->id)
        ->and($pet->fresh()->posts_count)->toBe(1);

    $this->actingAs($owner)
        ->deleteJson(route('pets.posts.detach', ['pet' => $pet, 'post' => $post]))
        ->assertOk()
        ->assertJsonPath('pet_id', null);

    expect($post->fresh()->pet_id)->toBeNull()
        ->and($pet->fresh()->posts_count)->toBe(0);
});

it('denies non-owners from attaching pets to posts', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);
    $post = Post::factory()->for($owner)->create(['pet_id' => null, 'tagged_pets' => []]);

    $this->actingAs($intruder)
        ->postJson(route('pets.posts.attach', ['pet' => $pet, 'post' => $post]))
        ->assertForbidden();
});
