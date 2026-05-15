<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Groups\Group;
use App\Models\Groups\GroupMember;
use App\Models\Identity\User;
use App\Services\GroupService;
use Illuminate\Support\Facades\Gate;

class UpdateGroupMemberRoleAction
{
    public function __construct(private readonly GroupService $groups) {}

    public function handle(User $actor, Group $group, GroupMember|int $membership, string $role): GroupMember
    {
        Gate::forUser($actor)->authorize('updateMemberRole', $group);

        return $this->groups->updateRole($actor, $group, $membership, $role);
    }
}
