<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function view(?User $viewer, Post $post): bool
    {
        $author = $post->author;

        if ((bool) $author->is_banned) {
            return false;
        }

        if ($viewer?->id === $author->id) {
            return true;
        }

        if ($viewer?->hasAnyRole(['admin', 'moderator'])) {
            return true;
        }

        if ($viewer && ($viewer->hasBlocked($author) || $author->hasBlocked($viewer))) {
            return false;
        }

        if ((bool) $author->is_private && ! ($viewer && $viewer->isFollowing($author))) {
            return false;
        }

        return match ($post->visibility) {
            Post::VISIBILITY_PRIVATE => $viewer?->id === $post->user_id,
            Post::VISIBILITY_FOLLOWERS => $viewer !== null && $viewer->isFollowing($author),
            default => true,
        };
    }

    public function create(User $user): bool
    {
        return ! (bool) $user->is_banned;
    }

    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function pin(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function report(User $user, Post $post): bool
    {
        return $user->id !== $post->user_id;
    }
}
