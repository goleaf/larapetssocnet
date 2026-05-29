<?php

namespace App\Jobs;

use App\Actions\Posts\PublishPostAction;
use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\PostMentionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class PublishScheduledPostJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $postId)
    {
        $this->afterCommit();
    }

    public function handle(PublishPostAction $publish, PostMentionService $mentions): void
    {
        $lock = Cache::store('database')->lock("posts:publish-scheduled:{$this->postId}", 60);

        if (! $lock->get()) {
            return;
        }

        try {
            $post = Post::query()
                ->with('author')
                ->find($this->postId);

            if (! $post instanceof Post || $post->status !== PostStatus::Scheduled) {
                return;
            }

            if ($post->scheduled_publish_at === null || $post->scheduled_publish_at->isFuture()) {
                return;
            }

            $author = $post->author;

            if (! $author instanceof User) {
                return;
            }

            $published = $publish->handle($author, $post, $post->scheduled_publish_at);

            $mentions->sync($published, $author, notifyExistingMentions: true);

            FeedFanOutJob::dispatch((int) $published->getKey())->afterCommit();
        } finally {
            $lock->release();
        }
    }
}
