<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\User;
use App\Notifications\FollowRequestApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PrivacyToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_profile_page_shows_locked_state_to_guests(): void
    {
        $user = User::factory()->create(['is_private' => true]);

        $this->get(route('profile.show', ['user' => $user]))
            ->assertOk()
            ->assertSee('This account is private')
            ->assertSee('noindex, nofollow', false);
    }

    public function test_private_profile_visible_to_accepted_followers(): void
    {
        $owner = User::factory()->create(['is_private' => true]);
        $follower = User::factory()->create();

        Follow::query()->create([
            'follower_id' => $follower->id,
            'following_id' => $owner->id,
            'status' => 'accepted',
            'created_at' => now(),
        ]);

        $this->actingAs($follower)
            ->get(route('profile.show', ['user' => $owner]))
            ->assertOk()
            ->assertDontSee('This account is private');
    }

    public function test_following_private_user_creates_pending_request(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['is_private' => true]);

        $this->actingAs($actor)
            ->postJson(route('users.follow', ['user' => $target]))
            ->assertOk()
            ->assertJsonPath('follow_status', 'pending');

        $this->assertDatabaseHas('follows', [
            'follower_id' => $actor->id,
            'following_id' => $target->id,
            'status' => 'pending',
        ]);
    }

    public function test_toggle_private_to_public_auto_approves_pending_requests(): void
    {
        Notification::fake();

        $owner = User::factory()->create([
            'is_private' => true,
            'followers_count' => 0,
            'follow_requests_count' => 2,
        ]);
        $requesterA = User::factory()->create();
        $requesterB = User::factory()->create();

        Follow::query()->create([
            'follower_id' => $requesterA->id,
            'following_id' => $owner->id,
            'status' => 'pending',
            'created_at' => now(),
        ]);
        Follow::query()->create([
            'follower_id' => $requesterB->id,
            'following_id' => $owner->id,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->actingAs($owner)
            ->postJson(route('privacy.toggle'))
            ->assertOk()
            ->assertJsonPath('is_private', false)
            ->assertJsonPath('auto_approved', 2);

        $this->assertDatabaseHas('follows', [
            'follower_id' => $requesterA->id,
            'following_id' => $owner->id,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('follows', [
            'follower_id' => $requesterB->id,
            'following_id' => $owner->id,
            'status' => 'accepted',
        ]);

        $owner->refresh();
        $requesterA->refresh();
        $requesterB->refresh();

        $this->assertSame(2, (int) $owner->followers_count);
        $this->assertSame(0, (int) $owner->follow_requests_count);
        $this->assertSame(1, (int) $requesterA->following_count);
        $this->assertSame(1, (int) $requesterB->following_count);

        Notification::assertSentTo($requesterA, FollowRequestApproved::class);
        Notification::assertSentTo($requesterB, FollowRequestApproved::class);
    }

    public function test_toggle_public_to_private_updates_flag(): void
    {
        $user = User::factory()->create(['is_private' => false]);

        $this->actingAs($user)
            ->postJson(route('privacy.toggle'))
            ->assertOk()
            ->assertJsonPath('is_private', true);

        $this->assertTrue((bool) $user->fresh()->is_private);
    }
}
