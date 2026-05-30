<?php

namespace App\Services;

use App\Actions\Posts\PublishPostAction;
use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Cache;

class ScheduledPostPublisherService
{
    public function __construct(
        private readonly PublishPostAction $publish,
        private readonly PostMentionService $mentions,
        private readonly FeedFanOutService $feedFanOut,
    ) {}

    public function publish(int $postId): bool
    {
        $lock = Cache::store('database')->lock("posts:publish-scheduled:{$postId}", 60);

        if (! $lock->get()) {
            return false;
        }

        try {
            $post = Post::query()
                ->with('author')
                ->find($postId);

            if (! $post instanceof Post || $post->status !== PostStatus::Scheduled) {
                return false;
            }

            if ($post->scheduled_publish_at === null || $post->scheduled_publish_at->isFuture()) {
                return false;
            }

            $author = $post->author;

            if (! $author instanceof User) {
                return false;
            }

            $published = $this->publish->handle($author, $post, $post->scheduled_publish_at);

            $this->mentions->sync($published, $author, notifyExistingMentions: true);
            $this->feedFanOut->fanOutPost((int) $published->getKey());

            return true;
        } finally {
            $lock->release();
        }
    }
}
