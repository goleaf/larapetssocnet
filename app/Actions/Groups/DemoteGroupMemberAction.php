<?php

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\GroupService;
use Illuminate\Support\Facades\Gate;

class DemoteGroupMemberAction
{
    public function __construct(private readonly GroupService $groups) {}

    public function handle(User $actor, Group $group, GroupMember|int $membership): GroupMember
    {
        Gate::forUser($actor)->authorize('updateMemberRole', $group);

        return $this->groups->demoteUser($actor, $group, $membership);
    }
}
