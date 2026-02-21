<?php

namespace App\Observers;

use App\Models\Post;
use App\Services\BadgeService;
use App\Services\CounterCacheService;
use App\Services\HashtagService;

class PostObserver
{
    public function __construct(
        private readonly HashtagService $hashtags,
        private readonly BadgeService $badges,
        private readonly CounterCacheService $counterCacheService,
    ) {}

    public function created(Post $post): void
    {
        $this->hashtags->syncHashtags($post);
        $post->author?->increment('posts_count');
        $post->pet?->increment('posts_count');

        if ($post->author) {
            $this->badges->checkAndAwardBadges($post->author);
        }
    }

    public function updated(Post $post): void
    {
        if ($post->wasChanged('body')) {
            $this->hashtags->syncHashtags($post);
        }
    }

    public function deleted(Post $post): void
    {
        $this->hashtags->detachAll($post);

        if ($post->author) {
            $this->counterCacheService->safeDecrement($post->author, 'posts_count');
        }

        if ($post->pet) {
            $this->counterCacheService->safeDecrement($post->pet, 'posts_count');
        }

        $post->clearMediaCollection('photos');
        $post->clearMediaCollection('videos');
        $post->clearMediaCollection('video');
    }
}
