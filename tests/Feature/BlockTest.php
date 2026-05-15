<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_block_and_unblock_another_user(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->postJson(route('users.block', ['user' => $target]))
            ->assertOk()
            ->assertJsonPath('data.is_blocked', true);

        $this->assertDatabaseHas('blocks', [
            'blocker_id' => $actor->id,
            'blocked_id' => $target->id,
        ]);

        $this->assertTrue($actor->fresh()->hasBlocked($target));

        $this->actingAs($actor)
            ->deleteJson(route('users.unblock', ['user' => $target]))
            ->assertOk()
            ->assertJsonPath('data.is_blocked', false);

        $this->assertDatabaseMissing('blocks', [
            'blocker_id' => $actor->id,
            'blocked_id' => $target->id,
        ]);

        $this->assertFalse($actor->fresh()->hasBlocked($target));
    }

    public function test_cannot_block_self(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->postJson(route('users.block', ['user' => $actor]))
            ->assertStatus(403);
    }

    public function test_cannot_block_admin_users(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        Role::findOrCreate('admin', 'web');
        $target->assignRole('admin');

        $this->actingAs($actor)
            ->postJson(route('users.block', ['user' => $target]))
            ->assertStatus(403);
    }

    public function test_blocking_removes_follow_rows_in_both_directions(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $actor->follow($target);
        $target->follow($actor);

        $this->actingAs($actor)
            ->postJson(route('users.block', ['user' => $target]))
            ->assertOk();

        $this->assertFalse($actor->fresh()->isFollowing($target));
        $this->assertFalse($target->fresh()->isFollowing($actor));
    }
}
