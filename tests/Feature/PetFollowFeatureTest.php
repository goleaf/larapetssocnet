<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetFollowFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_follow_pet(): void
    {
        $owner = User::factory()->create();
        $follower = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create([
            'followers_count' => 0,
        ]);

        $this->actingAs($follower)
            ->postJson(route('pets.follow', $pet->getKey()))
            ->assertOk()
            ->assertJsonPath('followed', true)
            ->assertJsonPath('followers_count', 1);

        $this->assertDatabaseHas('pet_followers', [
            'pet_id' => $pet->id,
            'user_id' => $follower->id,
        ]);

        expect($follower->fresh()->following_pets_count)->toBe(1);
        expect($pet->fresh()->followers_count)->toBe(1);
    }

    public function test_authenticated_user_can_unfollow_pet(): void
    {
        $owner = User::factory()->create();
        $follower = User::factory()->create([
            'following_pets_count' => 0,
        ]);
        $pet = Pet::factory()->for($owner)->create([
            'followers_count' => 0,
        ]);

        $follower->followPet($pet);

        $this->actingAs($follower)
            ->deleteJson(route('pets.unfollow', $pet->getKey()))
            ->assertOk()
            ->assertJsonPath('followed', false)
            ->assertJsonPath('followers_count', 0);

        $this->assertDatabaseMissing('pet_followers', [
            'pet_id' => $pet->id,
            'user_id' => $follower->id,
        ]);

        expect($follower->fresh()->following_pets_count)->toBe(0);
        expect($pet->fresh()->followers_count)->toBe(0);
    }

    public function test_owner_cannot_follow_own_pet(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->postJson(route('pets.follow', $pet->getKey()))
            ->assertForbidden();
    }

    public function test_follow_pet_is_idempotent_for_existing_follow(): void
    {
        $owner = User::factory()->create();
        $follower = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create([
            'followers_count' => 0,
        ]);

        $this->actingAs($follower)->postJson(route('pets.follow', $pet->getKey()))->assertOk();
        $this->actingAs($follower)->postJson(route('pets.follow', $pet->getKey()))->assertOk();

        $this->assertSame(
            1,
            $pet->followers()->whereKey($follower->id)->count()
        );
    }
}
