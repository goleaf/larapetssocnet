<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Notifications\Database\Posts\NewReaction;
use App\Notifications\Database\Posts\ReactionBatchNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ReactionNotificationService
{
    public function send(int $postId, int $reactorId, string $reactionType): bool
    {
        if (! Cache::add($this->cacheKey($postId), true, now()->addMinute())) {
            return false;
        }

        $post = Post::query()
            ->select(['id', 'user_id', 'body', 'uuid'])
            ->with(['author:id,name,username,notification_preferences'])
            ->find($postId);

        $reactor = User::query()
            ->select(['id', 'name', 'username'])
            ->find($reactorId);

        $author = $post?->author;

        if (! $post instanceof Post || ! $reactor instanceof User || ! $author instanceof User) {
            return false;
        }

        if ((int) $post->user_id === (int) $reactor->getKey()) {
            return false;
        }

        $normalizedType = Reaction::normalizeType($reactionType);

        $reactionStillExists = Reaction::query()
            ->where('reactable_type', $post->getMorphClass())
            ->where('reactable_id', $post->getKey())
            ->where('user_id', $reactor->getKey())
            ->where('type', $normalizedType)
            ->exists();

        if (! $reactionStillExists) {
            return false;
        }

        $recentReactions = $this->recentReactions($post);

        if ($recentReactions->isEmpty()) {
            return false;
        }

        if (! $author->notificationEnabled('post_likes')) {
            return false;
        }

        $notificationPost = $post->withoutRelation('author');

        if ($recentReactions->count() === 1) {
            $reaction = $recentReactions->first();
            $notificationUser = $this->relationLightUser($reaction?->user ?? $reactor);

            $author->notify(new NewReaction($notificationUser, $notificationPost, (string) ($reaction?->type ?? $normalizedType)));

            return true;
        }

        $author->notify(new ReactionBatchNotification(
            $this->relationLightUser($recentReactions->first()?->user ?? $reactor),
            $notificationPost,
            $recentReactions->count(),
        ));

        return true;
    }

    private function cacheKey(int $postId): string
    {
        return 'post-reaction-notification:'.$postId;
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
