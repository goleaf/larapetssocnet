<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\GroupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prevents admins from promoting members to admin role directly', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
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

    GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $admin->id,
        'role' => 'admin',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $membership = GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $service = app(GroupService::class);

    expect(fn () => $service->updateRole($admin, $group, $membership, 'admin'))
        ->toThrow(AuthorizationException::class);
});

it('allows owners to promote members to admin role', function () {
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

    $membership = GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $service = app(GroupService::class);

    $updated = $service->updateRole($owner, $group, $membership, 'admin');

    expect($updated->role?->value ?? $updated->role)->toBe('admin');
});
