<?php

namespace App\Jobs;

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Notifications\NewReaction;
use App\Notifications\ReactionBatchNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class SendReactionNotificationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 60;

    public function __construct(
        public readonly int $postId,
        public readonly int $reactorId,
        public readonly string $reactionType,
    ) {
        $this->afterCommit();
        $this->onConnection('database');
    }

    public function uniqueId(): string
    {
        return 'post-reaction-notification:'.$this->postId;
    }

    public function handle(): void
    {
        $post = Post::query()
            ->select(['id', 'user_id', 'body', 'uuid'])
            ->with(['author:id,name,username,notification_preferences'])
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

        $recentReactions = $this->recentReactions($post);

        if ($recentReactions->isEmpty()) {
            return;
        }

        if (! $author->notificationEnabled('post_likes')) {
            return;
        }

        $notificationPost = $post->withoutRelation('author');

        if ($recentReactions->count() === 1) {
            $reaction = $recentReactions->first();
            $notificationUser = $this->relationLightUser($reaction?->user ?? $reactor);

            $author->notify(new NewReaction($notificationUser, $notificationPost, (string) ($reaction?->type ?? $normalizedType)));

            return;
        }

        $author->notify(new ReactionBatchNotification(
            $this->relationLightUser($recentReactions->first()?->user ?? $reactor),
            $notificationPost,
            $recentReactions->count(),
        ));
    }

    /**
     * @return Collection<int, Reaction>
     */
    private function recentReactions(Post $post): Collection
    {
        return Reaction::query()
            ->with(['user:id,name,username'])
            ->where('reactable_type', $post->getMorphClass())
            ->where('reactable_id', $post->getKey())
            ->where('user_id', '!=', $post->user_id)
            ->where('created_at', '>=', now()->subMinute())
            ->latest('created_at')
            ->limit(12)
            ->get();
    }

    private function relationLightUser(User $user): User
    {
        return $user->withoutRelation([
            'media',
            'followers',
            'following',
            'acceptedFollowers',
            'acceptedFollowing',
            'posts',
        ]);
    }
}
