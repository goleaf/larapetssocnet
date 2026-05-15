<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Services\GroupService;
use Illuminate\Support\Facades\Gate;

class CancelGroupJoinRequestAction
{
    public function __construct(private readonly GroupService $groups) {}

    public function handle(User $actor, Group $group): bool
    {
        Gate::forUser($actor)->authorize('leave', $group);

        return $this->groups->cancelRequest($actor, $group);
    }
}
