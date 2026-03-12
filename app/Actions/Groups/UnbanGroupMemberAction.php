<?php

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupService;
use Illuminate\Support\Facades\Gate;

class UnbanGroupMemberAction
{
    public function __construct(private readonly GroupService $groups) {}

    public function handle(User $actor, Group $group, User|int $target): bool
    {
        Gate::forUser($actor)->authorize('banMember', $group);

        return $this->groups->unbanUser($actor, $group, $target);
    }
}
