<?php

use App\Enums\GroupMemberStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\GroupVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('private groups allow viewing but restrict posts to members', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $group = Group::factory()->private()->create([
        'owner_user_id' => $owner->getKey(),
        'owner_id' => $owner->getKey(),
    ]);

    $service = app(GroupVisibilityService::class);

    expect($service->canViewGroup($viewer, $group))->toBeTrue();
    expect($service->canViewGroupPosts($viewer, $group))->toBeFalse();

    GroupMember::factory()->create([
        'group_id' => $group->getKey(),
        'user_id' => $viewer->getKey(),
        'status' => GroupMemberStatus::Active->value,
    ]);

    expect($service->canViewGroupPosts($viewer, $group))->toBeTrue();
});

test('banned members cannot view groups', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $group = Group::factory()->public()->create([
        'owner_user_id' => $owner->getKey(),
        'owner_id' => $owner->getKey(),
    ]);

    GroupMember::factory()->banned()->create([
        'group_id' => $group->getKey(),
        'user_id' => $viewer->getKey(),
    ]);

    $service = app(GroupVisibilityService::class);

    expect($service->canViewGroup($viewer, $group))->toBeFalse();
});
