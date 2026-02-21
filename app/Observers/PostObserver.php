<?php

namespace App\Observers;

use App\Models\Post;
use App\Services\BadgeService;
use App\Services\HashtagService;

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

        $post->author->increment('posts_count');

        if ($post->pet_id) {
            $post->pet->increment('posts_count');
        }

        $this->badges->checkAndAwardBadges($post->author);

        activity()
            ->causedBy($post->author)
            ->performedOn($post)
            ->log('created');
    }

    /**
     * Handle the Post "updated" event.
     */
    public function updated(Post $post): void
    {
        if ($post->wasChanged('body')) {
            $this->hashtags->syncHashtags($post);
        }

        activity()
            ->causedBy(auth()->user())
            ->performedOn($post)
            ->log('updated');
    }

    /**
     * Handle the Post "deleted" event.
     */
    public function deleted(Post $post): void
    {
        $this->hashtags->detachAll($post);

        $post->author->decrement('posts_count');

        if ($post->pet) {
            $post->pet->decrement('posts_count');
        }

        activity()
            ->causedBy(auth()->user())
            ->performedOn($post)
            ->log('deleted');
    }
}
