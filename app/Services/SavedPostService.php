<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Support\Facades\DB;

class SavedPostService
{
    public function toggle(User $user, Post $post): bool
    {
        return DB::transaction(function () use ($user, $post): bool {
            $exists = $user->savedPosts()
                ->where('posts.id', $post->id)
                ->exists();

            if ($exists) {
                $user->savedPosts()->detach($post->id);
                $post->decrementCounter('save_count');

                return false;
            }

            $user->savedPosts()->attach($post->id);
            $post->incrementCounter('save_count');

            return true;
        });
    }
}
