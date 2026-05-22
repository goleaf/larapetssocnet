<?php

namespace App\Observers;

use App\Enums\PostStatus;
use App\Events\PostCreated;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\BadgeService;
use App\Services\HashtagService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PostObserver
{
    public function __construct(
        private HashtagService $hashtags,
        private BadgeService $badges
    ) {}

    /**
     * Handle the Post "created" event.
     */
    public function created(Post $post): void
    {
        $author = $this->postAuthor($post);

        $this->hashtags->syncHashtags($post);

        PostCreated::dispatch($post);

        $this->bustFeedCache($post);

        $author->increment('posts_count');

        if ($this->isScheduledStatus($post->getAttribute('status'))) {
            $author->incrementCounter('scheduled_posts_count');
        }

        if ($post->pet_id) {
            $post->pet->increment('posts_count');
        }

        $this->badges->checkAndAwardBadges($author);

        $this->logActivity('created', $post, $author);
    }

    /**
     * Handle the Post "updated" event.
     */
    public function updated(Post $post): void
    {
        $previousHashtagIds = null;

        if ($post->wasChanged('body')) {
            $previousHashtagIds = $post->hashtags()->pluck('hashtags.id')->all();
        }

        $transitioned = false;

        if ($post->wasChanged(['status', 'published_at'])) {
            $originalStatus = $post->getOriginal('status');
            $statusValue = $originalStatus instanceof PostStatus
                ? $originalStatus->value
                : (string) $originalStatus;

            $wasEligible = $this->hashtags->isEligibleForUsageState(
                $statusValue,
                $post->getOriginal('published_at'),
                $post->getOriginal('deleted_at')
            );
            $isEligible = $this->hashtags->isEligibleForUsage($post);
            $transitioned = $wasEligible !== $isEligible;
        }

        if ($post->wasChanged('body')) {
            $this->hashtags->syncHashtags($post, ! $transitioned);
        }

        if ($transitioned) {
            $this->hashtags->syncUsageForEligibilityChange($post, $wasEligible, $isEligible, $previousHashtagIds);
        }

        if ($post->wasChanged('status')) {
            $wasScheduled = $this->isScheduledStatus($post->getOriginal('status'));
            $author = $this->postAuthor($post);
            $isScheduled = $this->isScheduledStatus($post->getAttribute('status'));

            if (! $wasScheduled && $isScheduled) {
                $author->incrementCounter('scheduled_posts_count');
            }

            if ($wasScheduled && ! $isScheduled) {
                $author->decrementCounter('scheduled_posts_count');
            }
        }

        $this->bustFeedCache($post);

        $this->logActivity('updated', $post, auth()->user());
    }

    /**
     * Handle the Post "deleting" event.
     */
    public function deleting(Post $post): void
    {
        if ($post->isForceDeleting()) {
            $post->comments()->withTrashed()->forceDelete();
            $post->postMedia()->withTrashed()->forceDelete();

            return;
        }

        $post->comments()->get()->each->delete();
        $post->postMedia()->get()->each->delete();
    }

    public function restoring(Post $post): void
    {
        $post->comments()->withTrashed()->restore();
        $post->postMedia()->withTrashed()->restore();
    }

    public function restored(Post $post): void
    {
        $author = $this->postAuthor($post);

        $this->hashtags->syncHashtags($post);

        $author->increment('posts_count');

        if ($this->isScheduledStatus($post->getAttribute('status'))) {
            $author->incrementCounter('scheduled_posts_count');
        }

        if ($post->pet) {
            $post->pet->increment('posts_count');
        }

        $this->bustFeedCache($post);

        $this->logActivity('restored', $post, auth()->user());
    }

    /**
     * Handle the Post "deleted" event.
     */
    public function deleted(Post $post): void
    {
        $author = $this->postAuthor($post);
        $originalStatus = $post->getOriginal('status');
        $statusValue = $originalStatus instanceof PostStatus
            ? $originalStatus->value
            : (string) $originalStatus;

        $wasEligible = $this->hashtags->isEligibleForUsageState(
            $statusValue,
            $post->getOriginal('published_at'),
            null
        );

        $this->hashtags->detachAll($post, $wasEligible);

        $author->decrement('posts_count');

        if ($this->isScheduledStatus($originalStatus)) {
            $author->decrementCounter('scheduled_posts_count');
        }

        if ($post->pet) {
            $post->pet->decrement('posts_count');
        }

        $this->bustFeedCache($post);

        $this->logActivity('deleted', $post, auth()->user());
    }

    private function bustFeedCache(Post $post): void
    {
        Cache::forget('feed:posts');
        Cache::forget('feed:posts:user:'.$post->user_id);
    }

    private function logActivity(string $description, Post $post, mixed $causer): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        activity()
            ->causedBy($causer)
            ->performedOn($post)
            ->log($description);
    }

    private function postAuthor(Post $post): User
    {
        return User::query()
            ->whereKey($post->getAttribute('user_id'))
            ->firstOrFail();
    }

    private function isScheduledStatus(mixed $status): bool
    {
        return $this->statusValue($status) === PostStatus::Scheduled->value;
    }

    private function statusValue(mixed $status): string
    {
        return $status instanceof PostStatus ? $status->value : (string) $status;
    }
}
