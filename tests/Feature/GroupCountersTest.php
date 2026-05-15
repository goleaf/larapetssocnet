<?php

use App\Enums\GroupMemberRole;
use App\Enums\GroupMemberStatus;
use App\Models\Groups\Group;
use App\Models\Groups\GroupMember;
use App\Models\Identity\User;
use App\Services\SyncGroupCountersService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('members count updates as users join and leave', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $group = Group::factory()->public()->create([
        'owner_user_id' => $owner->getKey(),
        'owner_id' => $owner->getKey(),
        'members_count' => 0,
    ]);

    GroupMember::factory()->create([
        'group_id' => $group->getKey(),
        'user_id' => $owner->getKey(),
        'role' => GroupMemberRole::Owner->value,
        'status' => GroupMemberStatus::Active->value,
        'joined_at' => now(),
    ]);

    app(SyncGroupCountersService::class)->syncMembersCount($group);
    $group->refresh();
    expect((int) $group->members_count)->toBe(1);

    $this->actingAs($member)
        ->post(route('groups.join', $group->slug))
        ->assertRedirect();

    $group->refresh();
    expect((int) $group->members_count)->toBe(2);

    $this->actingAs($member)
        ->delete(route('groups.leave', $group->slug))
        ->assertRedirect();

    $group->refresh();
    expect((int) $group->members_count)->toBe(1);
});
