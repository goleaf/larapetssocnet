<?php

namespace App\Actions\Posts;

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;

class RecordPostViewAction
{
    public function handle(?User $viewer, Post $post, string $context): bool
    {
        if (! $viewer instanceof User) {
            return false;
        }

        if (! in_array($context, ['feed', 'profile'], true)) {
            return false;
        }

        if ((int) $viewer->getKey() === (int) $post->user_id) {
            return false;
        }

        $statusAttribute = $post->getAttribute('status');
        $status = $statusAttribute instanceof PostStatus ? $statusAttribute : PostStatus::tryFrom((string) $statusAttribute);

        if ($status !== PostStatus::Published || $post->trashed()) {
            return false;
        }

        return $post->incrementCounter('view_count');
    }
}
