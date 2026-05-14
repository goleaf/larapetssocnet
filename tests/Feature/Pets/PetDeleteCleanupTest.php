<?php

use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cleans up followers and post links when deleting a pet', function (): void {
    $owner = User::factory()->create();
    $follower = User::factory()->create(['following_pets_count' => 0]);
    $pet = Pet::factory()->for($owner)->create(['followers_count' => 0]);

    $follower->followPet($pet);

    $post = Post::factory()->for($owner)->create([
        'pet_id' => $pet->id,
        'tagged_pets' => [$pet->id],
    ]);

    $this->actingAs($owner)
        ->delete(route('pets.destroy', $pet))
        ->assertRedirect(route('pets.index'));

    $this->assertSoftDeleted('pets', ['id' => $pet->id]);
    $this->assertDatabaseMissing('pet_followers', [
        'pet_id' => $pet->id,
        'user_id' => $follower->id,
    ]);

    expect($follower->fresh()->following_pets_count)->toBe(0);
    expect($post->fresh()->pet_id)->toBeNull()
        ->and($post->fresh()->tagged_pets ?? [])->not()->toContain($pet->id);
});
