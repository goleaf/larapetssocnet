<?php

declare(strict_types=1);

namespace App\Actions\Engagement;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\SavedPostService;

class ToggleSavedPostAction
{
    public function __construct(private readonly SavedPostService $saves) {}

    public function handle(User $actor, Post $post): bool
    {
        return $this->saves->toggle($actor, $post);
    }
}
