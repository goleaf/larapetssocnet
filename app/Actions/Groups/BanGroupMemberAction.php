<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Groups\Group;
use App\Models\Groups\GroupMember;
use App\Models\Identity\User;
use App\Services\GroupService;
use Illuminate\Support\Facades\Gate;

class BanGroupMemberAction
{
    public function __construct(private readonly GroupService $groups) {}

    public function handle(User $actor, Group $group, User|GroupMember|int $target, ?string $reason = null): GroupMember
    {
        Gate::forUser($actor)->authorize('banMember', $group);

        return $this->groups->banUser($actor, $group, $target, $reason);
    }
}
