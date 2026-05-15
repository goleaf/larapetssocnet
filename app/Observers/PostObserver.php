<?php

namespace App\Observers;

use App\Enums\PostStatus;
use App\Events\PostCreated;
use App\Models\Content\Post;
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
        $this->hashtags->syncHashtags($post);

        PostCreated::dispatch($post);

        $this->bustFeedCache($post);

        $post->author->increment('posts_count');

        if ($post->pet_id) {
            $post->pet->increment('posts_count');
        }

        $this->badges->checkAndAwardBadges($post->author);

        $this->logActivity('created', $post, $post->author);
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
        $this->hashtags->syncHashtags($post);

        $post->author->increment('posts_count');

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

        $post->author->decrement('posts_count');

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
}
