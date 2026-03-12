<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class PostActivityService
{
    public function log(User $actor, Post $post, string $event): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        activity()
            ->causedBy($actor)
            ->performedOn($post)
            ->log($event);
    }
}
