<?php

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupCoverImageService;

class RemoveGroupCoverAction
{
    public function __construct(private readonly GroupCoverImageService $covers) {}

    public function handle(User $actor, Group $group): void
    {
        $this->covers->removeCover($actor, $group);
    }
}
