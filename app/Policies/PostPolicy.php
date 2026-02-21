<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function view(?User $user, Post $post): bool
    {
        if ($post->visibility === Post::VISIBILITY_PUBLIC) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ((int) $post->user_id === (int) $user->getKey()) {
            return true;
        }

        if ($post->visibility === Post::VISIBILITY_FOLLOWERS && $post->user) {
            return $user->isFollowing($post->user);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return ! empty($user->getKey());
    }

    public function update(User $user, Post $post): bool
    {
        return (int) $post->user_id === (int) $user->getKey() || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function pin(User $user, Post $post): bool
    {
        return (int) $post->user_id === (int) $user->getKey();
    }

    public function report(User $user, Post $post): bool
    {
        return (int) $post->user_id !== (int) $user->getKey();
    }
}
