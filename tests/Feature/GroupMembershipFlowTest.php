<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupMembershipFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_group_join_and_leave_flow(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $group = Group::factory()->create([
            'owner_user_id' => $owner->id,
            'privacy' => 'public',
        ]);

        GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($member)
            ->post(route('groups.join', $group->slug))
            ->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'status' => 'active',
        ]);

        $this->actingAs($member)
            ->delete(route('groups.leave', $group->slug))
            ->assertRedirect();

        $this->assertDatabaseMissing('group_members', [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'status' => 'active',
        ]);
    }

    public function test_private_group_join_creates_pending_request_then_admin_approves(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $group = Group::factory()->create([
            'owner_user_id' => $owner->id,
            'privacy' => 'private',
        ]);

        GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($member)
            ->post(route('groups.join', $group->slug))
            ->assertRedirect();

        $pending = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->assertSame('pending', $pending->status);

        $this->actingAs($admin)
            ->post(route('groups.requests.approve', ['group' => $group->slug, 'membership' => $pending->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'id' => $pending->id,
            'status' => 'active',
        ]);
    }

    public function test_secret_group_cannot_be_joined_without_invite(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $group = Group::factory()->create([
            'owner_user_id' => $owner->id,
            'privacy' => 'secret',
        ]);

        GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($member)
            ->post(route('groups.join', $group->slug))
            ->assertRedirect();

        $this->assertDatabaseMissing('group_members', [
            'group_id' => $group->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_admin_can_promote_member_to_moderator_and_ban_member(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $group = Group::factory()->create([
            'owner_user_id' => $owner->id,
            'privacy' => 'public',
        ]);

        GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $membership = GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('groups.members.promote', ['group' => $group->slug, 'membership' => $membership->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'id' => $membership->id,
            'role' => 'moderator',
        ]);

        $this->actingAs($admin)
            ->post(route('groups.bans.store', ['group' => $group->slug]), [
                'user_id' => $member->id,
                'reason' => 'Test ban',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'status' => 'banned',
        ]);
    }
}
