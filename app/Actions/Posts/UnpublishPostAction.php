<?php

namespace App\Actions\Posts;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Services\PostActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UnpublishPostAction
{
    public function __construct(private readonly PostActivityService $activity) {}

    public function handle(User $actor, Post $post): Post
    {
        Gate::forUser($actor)->authorize('unpublish', $post);

        return DB::transaction(function () use ($actor, $post): Post {
            $post->update([
                'status' => PostStatus::Draft->value,
                'published_at' => null,
            ]);

            $this->activity->log($actor, $post, 'unpublished');

            return $post->refresh();
        });
    }
}
