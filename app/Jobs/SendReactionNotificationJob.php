<?php

namespace App\Jobs;

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Notifications\NewReaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendReactionNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $postId,
        public readonly int $reactorId,
        public readonly string $reactionType,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $post = Post::query()
            ->with('author')
            ->find($this->postId);

        $reactor = User::query()
            ->select(['id', 'name', 'username'])
            ->find($this->reactorId);

        $author = $post?->author;

        if (! $post instanceof Post || ! $reactor instanceof User || ! $author instanceof User) {
            return;
        }

        if ((int) $post->user_id === (int) $reactor->getKey()) {
            return;
        }

        $normalizedType = Reaction::normalizeType($this->reactionType);

        $reactionStillExists = Reaction::query()
            ->where('reactable_type', $post->getMorphClass())
            ->where('reactable_id', $post->getKey())
            ->where('user_id', $reactor->getKey())
            ->where('type', $normalizedType)
            ->exists();

        if (! $reactionStillExists) {
            return;
        }

        if (! $author->notificationEnabled('post_likes')) {
            return;
        }

        $notificationPost = $post->withoutRelation('author');
        $notificationUser = $reactor->withoutRelation([
            'media',
            'followers',
            'following',
            'acceptedFollowers',
            'acceptedFollowing',
        ]);

        $author->notify(new NewReaction($notificationUser, $notificationPost, $normalizedType));
    }
}
