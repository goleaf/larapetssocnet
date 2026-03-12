<?php

namespace App\Actions\Engagement;

use App\Models\Post;
use App\Models\User;
use App\Services\SavedPostService;

class ToggleSavedPostAction
{
    public function __construct(private readonly SavedPostService $saves) {}

    public function handle(User $actor, Post $post): bool
    {
        return $this->saves->toggle($actor, $post);
    }
}
