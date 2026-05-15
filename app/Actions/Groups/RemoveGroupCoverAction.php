<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Services\GroupCoverImageService;

class RemoveGroupCoverAction
{
    public function __construct(private readonly GroupCoverImageService $covers) {}

    public function handle(User $actor, Group $group): void
    {
        $this->covers->removeCover($actor, $group);
    }
}
