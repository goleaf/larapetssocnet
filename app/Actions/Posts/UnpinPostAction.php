<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\PinService;
use App\Services\PostActivityService;
use Illuminate\Support\Facades\Gate;

class UnpinPostAction
{
    public function __construct(
        private readonly PinService $pins,
        private readonly PostActivityService $activity
    ) {}

    public function handle(User $actor, Post $post): Post
    {
        Gate::forUser($actor)->authorize('unpin', $post);

        $unpinned = $this->pins->unpin($actor, $post);
        $this->activity->log($actor, $post, 'unpinned');

        return $unpinned;
    }
}
