<?php

namespace App\Observers;

use App\Enums\PostStatus;
use App\Events\PostCreated;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\BadgeService;
use App\Services\HashtagService;
use Carbon\CarbonInterface;
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

        PostCreated::dispatch($post);

        $this->bustFeedCache($post);

        $author->increment('posts_count');
        $this->incrementAuthorActivityFromPost($author, $post, includeComments: true);
        $this->recordLatestPostCreatedAt($author, $post);

        if ($this->isScheduledStatus($post->getAttribute('status'))) {
            $author->incrementCounter('scheduled_posts_count');
        }

        if ($post->pet_id && blank($post->tagged_pets)) {
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
        $this->incrementAuthorActivityFromPost($author, $post, includeComments: false);
        $this->recordLatestPostCreatedAt($author, $post);

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
        $this->decrementAuthorActivityFromPost($author, $post, includeComments: false);
        $this->syncLatestPostCreatedAt($author);

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

    private function incrementAuthorActivityFromPost(User $author, Post $post, bool $includeComments): void
    {
        if ($includeComments) {
            $author->incrementCounter('post_comments_received_count', (int) ($post->getAttribute('comments_count') ?? 0));
        }

        $author->incrementCounter('post_reactions_received_count', (int) ($post->getAttribute('reactions_count') ?? 0));
    }

    private function decrementAuthorActivityFromPost(User $author, Post $post, bool $includeComments): void
    {
        if ($includeComments) {
            $author->decrementCounter('post_comments_received_count', (int) ($post->getAttribute('comments_count') ?? 0));
        }

        $author->decrementCounter('post_reactions_received_count', (int) ($post->getAttribute('reactions_count') ?? 0));
    }

    private function recordLatestPostCreatedAt(User $author, Post $post): void
    {
        if (! Schema::hasColumn('users', 'last_post_created_at')) {
            return;
        }

        $createdAt = $post->getAttribute('created_at');

        if (! $createdAt instanceof CarbonInterface) {
            return;
        }

        User::query()
            ->whereKey($author->getKey())
            ->where(function ($query) use ($createdAt): void {
                $query
                    ->whereNull('last_post_created_at')
                    ->orWhere('last_post_created_at', '<', $createdAt);
            })
            ->update(['last_post_created_at' => $createdAt]);

        $author->setAttribute('last_post_created_at', $createdAt);
    }

    private function syncLatestPostCreatedAt(User $author): void
    {
        if (! Schema::hasColumn('users', 'last_post_created_at')) {
            return;
        }

        User::query()
            ->whereKey($author->getKey())
            ->update([
                'last_post_created_at' => Post::query()
                    ->where('user_id', $author->getKey())
                    ->whereNull('deleted_at')
                    ->max('created_at'),
            ]);
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
