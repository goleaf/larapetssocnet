<?php

namespace App\Actions\Posts;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Services\PostActivityService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PublishPostAction
{
    public function __construct(private readonly PostActivityService $activity) {}

    public function handle(User $actor, Post $post, ?CarbonInterface $publishedAt = null): Post
    {
        Gate::forUser($actor)->authorize('publish', $post);

        return DB::transaction(function () use ($actor, $post, $publishedAt): Post {
            $post->update([
                'status' => PostStatus::Published->value,
                'published_at' => $publishedAt ?? now(),
            ]);

            $this->activity->log($actor, $post, 'published');

            return $post->refresh() ?? $post;
        });
    }
}
