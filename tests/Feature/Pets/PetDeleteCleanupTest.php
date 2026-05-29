<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Pets\Pet;
use App\Models\Pets\PetMilestone;
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
    $taggedPost = Post::factory()->for($owner)->create([
        'pet_id' => null,
        'tagged_pets' => [$pet->id],
    ]);
    $taggedPost->pets()->attach($pet->id);
    $milestone = PetMilestone::factory()->for($pet)->for($owner, 'user')->create();
    $listing = MarketplaceListing::factory()->for($owner, 'seller')->for($pet)->create([
        'listing_type' => 'adoption',
        'status' => MarketplaceListing::STATUS_ACTIVE,
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

    $this->assertSoftDeleted('posts', ['id' => $post->getKey()]);
    $this->assertSoftDeleted('posts', ['id' => $taggedPost->getKey()]);
    $this->assertSoftDeleted('pet_milestones', ['id' => $milestone->getKey()]);
    $this->assertSoftDeleted('marketplace_listings', ['id' => $listing->getKey()]);
});
