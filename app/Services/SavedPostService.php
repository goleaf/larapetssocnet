<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;

class SavedPostService
{
    public function toggle(User $user, Post $post): bool
    {
        $exists = $user->savedPosts()
            ->where('posts.id', $post->id)
            ->exists();

        if ($exists) {
            $user->savedPosts()->detach($post->id);

            return false;
        }

        $user->savedPosts()->attach($post->id);

        return true;
    }
}
