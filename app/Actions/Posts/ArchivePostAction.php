<?php

namespace App\Actions\Posts;

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\PostActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchivePostAction
{
    public function __construct(private readonly PostActivityService $activity) {}

    public function handle(User $actor, Post $post): Post
    {
        Gate::forUser($actor)->authorize('archive', $post);

        return DB::transaction(function () use ($actor, $post): Post {
            $post->update([
                'status' => PostStatus::Archived->value,
            ]);

            $this->activity->log($actor, $post, 'archived');

            return $post->refresh();
        });
    }
}
