<?php

use App\Models\Groups\Group;
use App\Models\Groups\GroupBan;
use App\Models\Groups\GroupMember;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows direct join for public groups and updates members count', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $group = Group::factory()->create([
        'owner_user_id' => $owner->id,
        'owner_id' => $owner->id,
        'privacy' => 'public',
        'type' => 'public',
        'members_count' => 0,
    ]);

    GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $this->actingAs($member)
        ->post(route('groups.join', $group->slug))
        ->assertRedirect();

    $group->refresh();

    $this->assertDatabaseHas('group_members', [
        'group_id' => $group->id,
        'user_id' => $member->id,
        'status' => 'active',
    ]);
    expect($group->members_count)->toBe(2);

    $this->actingAs($member)
        ->delete(route('groups.leave', $group->slug))
        ->assertRedirect();

    $group->refresh();
    expect($group->members_count)->toBe(1);
});

it('creates pending request for private groups and allows approval', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();

    $group = Group::factory()->create([
        'owner_user_id' => $owner->id,
        'owner_id' => $owner->id,
        'privacy' => 'private',
        'type' => 'private',
        'members_count' => 0,
    ]);

    GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $admin->id,
        'role' => 'admin',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $this->actingAs($member)
        ->post(route('groups.join', $group->slug))
        ->assertRedirect();

    $pending = GroupMember::query()
        ->where('group_id', $group->id)
        ->where('user_id', $member->id)
        ->firstOrFail();

    expect($pending->status?->value ?? $pending->status)->toBe('pending');

    $this->assertDatabaseHas('group_join_requests', [
        'group_id' => $group->id,
        'user_id' => $member->id,
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->post(route('groups.requests.approve', ['group' => $group->slug, 'membership' => $pending->id]))
        ->assertRedirect();

    $group->refresh();

    $this->assertDatabaseHas('group_members', [
        'id' => $pending->id,
        'status' => 'active',
    ]);
    $this->assertDatabaseHas('group_join_requests', [
        'group_id' => $group->id,
        'user_id' => $member->id,
        'status' => 'approved',
    ]);
    expect($group->members_count)->toBe(3);
});

it('allows a requester to cancel a pending join request', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $group = Group::factory()->create([
        'owner_user_id' => $owner->id,
        'owner_id' => $owner->id,
        'privacy' => 'private',
        'type' => 'private',
    ]);

    GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $this->actingAs($member)
        ->post(route('groups.join', $group->slug))
        ->assertRedirect();

    $this->actingAs($member)
        ->delete(route('groups.requests.cancel', $group->slug))
        ->assertRedirect();

    $this->assertDatabaseMissing('group_members', [
        'group_id' => $group->id,
        'user_id' => $member->id,
        'status' => 'pending',
    ]);
    $this->assertDatabaseMissing('group_join_requests', [
        'group_id' => $group->id,
        'user_id' => $member->id,
    ]);
});

it('prevents banned users from joining', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $group = Group::factory()->create([
        'owner_user_id' => $owner->id,
        'owner_id' => $owner->id,
        'privacy' => 'public',
        'type' => 'public',
    ]);

    GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    GroupBan::factory()->create([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'banned_by' => $owner->id,
    ]);

    $this->actingAs($member)
        ->post(route('groups.join', $group->slug))
        ->assertForbidden();
});

it('blocks non-managers from removing members', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $other = User::factory()->create();

    $group = Group::factory()->create([
        'owner_user_id' => $owner->id,
        'owner_id' => $owner->id,
        'privacy' => 'public',
        'type' => 'public',
    ]);

    GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $membership = GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $other->id,
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $this->actingAs($member)
        ->delete(route('groups.members.remove', ['group' => $group->slug, 'membership' => $membership->id]))
        ->assertForbidden();
});
