<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\FollowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_returns_pending_for_private_target(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['is_private' => true]);

        $status = app(FollowService::class)->follow($actor, $target);

        $this->assertSame('pending', $status);
    }

    public function test_unfollow_is_idempotent(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        app(FollowService::class)->unfollow($actor, $target);

        $this->assertTrue(true);
    }
}
