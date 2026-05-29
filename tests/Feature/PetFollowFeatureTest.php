<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
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
            ->postJson(route('pets.follow', $pet))
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

    public function test_guest_is_redirected_to_login_when_following_pet(): void
    {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        $this->post(route('pets.follow', $pet))
            ->assertRedirect(route('login'));
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
            ->deleteJson(route('pets.unfollow', $pet))
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
            ->postJson(route('pets.follow', $pet))
            ->assertForbidden();
    }

    public function test_follow_pet_is_idempotent_for_existing_follow(): void
    {
        $owner = User::factory()->create();
        $follower = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create([
            'followers_count' => 0,
        ]);

        $this->actingAs($follower)->postJson(route('pets.follow', $pet))->assertOk();
        $this->actingAs($follower)->postJson(route('pets.follow', $pet))->assertOk();

        $this->assertSame(
            1,
            $pet->followers()->whereKey($follower->id)->count()
        );
    }

    public function test_pet_follow_actions_use_social_follow_rate_limit(): void
    {
        RateLimiter::clear('hour:1');
        RateLimiter::clear('day:1');

        $follower = User::factory()->create(['id' => 1]);
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create([
            'followers_count' => 0,
        ]);

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $this->actingAs($follower)
                ->postJson(route('pets.follow', $pet))
                ->assertOk();
        }

        $this->actingAs($follower)
            ->postJson(route('pets.follow', $pet))
            ->assertTooManyRequests()
            ->assertJsonPath('success', false);
    }
}
