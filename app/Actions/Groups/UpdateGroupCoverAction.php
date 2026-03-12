<?php

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupCoverImageService;
use Illuminate\Http\UploadedFile;

class UpdateGroupCoverAction
{
    public function __construct(private readonly GroupCoverImageService $covers) {}

    public function handle(User $actor, Group $group, UploadedFile $file): void
    {
        $this->covers->updateCover($actor, $group, $file);
    }
}
