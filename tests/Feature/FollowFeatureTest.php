<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_follow_and_unfollow_user(): void
    {
        $follower = User::factory()->create();
        $followed = User::factory()->create();

        $this->actingAs($follower)
            ->postJson(route('users.follow', $followed))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('follow_status', 'following');

        $this->assertDatabaseHas('follows', [
            'follower_id' => $follower->getKey(),
            'following_id' => $followed->getKey(),
            'status' => 'accepted',
        ]);

        $this->actingAs($follower)
            ->postJson(route('users.unfollow', $followed))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('follow_status', 'none');

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $follower->getKey(),
            'following_id' => $followed->getKey(),
        ]);
    }
}
