<?php

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\GroupService;
use Illuminate\Support\Facades\Gate;

class JoinGroupAction
{
    public function __construct(private readonly GroupService $groups) {}

    public function handle(User $actor, Group $group, ?string $message = null): GroupMember
    {
        Gate::forUser($actor)->authorize('join', $group);

        return $this->groups->join($actor, $group, $message);
    }
}
