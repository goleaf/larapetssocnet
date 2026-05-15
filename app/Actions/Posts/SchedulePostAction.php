<?php

namespace App\Actions\Posts;

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\PostActivityService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SchedulePostAction
{
    public function __construct(private readonly PostActivityService $activity) {}

    public function handle(User $actor, Post $post, CarbonInterface $publishAt): Post
    {
        Gate::forUser($actor)->authorize('schedule', $post);

        return DB::transaction(function () use ($actor, $post, $publishAt): Post {
            $post->update([
                'status' => PostStatus::Scheduled->value,
                'published_at' => $publishAt,
            ]);

            $this->activity->log($actor, $post, 'scheduled');

            return $post->refresh();
        });
    }
}
