<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\BlockService;
use App\Services\CounterCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_interact_returns_false_when_users_have_block_relationship(): void
    {
        $service = app(BlockService::class);
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $service->block($actor, $target);

        $this->assertFalse($service->canInteract($actor->fresh(), $target->fresh()));
    }

    public function test_safe_decrement_never_decrements_below_zero(): void
    {
        $user = User::factory()->create(['followers_count' => 0]);

        app(CounterCacheService::class)->safeDecrement($user, 'followers_count');

        $this->assertSame(0, $user->fresh()->followers_count);
    }
}
