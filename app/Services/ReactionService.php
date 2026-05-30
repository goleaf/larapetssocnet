<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Notifications\NewReaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReactionService
{
    /**
     * @return array{action: 'added'|'changed'|'removed', current_reaction: ?string, likes_count: int, reactions_count: int, reaction_counts: array<string, int>}
     */
    public function react(User $user, Post $post, string $type): array
    {
        $normalizedType = Reaction::normalizeType($type);

        if (! in_array($normalizedType, Reaction::types(), true)) {
            throw ValidationException::withMessages(['type' => 'Invalid reaction type.']);
        }

        $result = DB::transaction(function () use ($user, $post, $normalizedType): array {
            $existing = Reaction::query()
                ->where('reactable_type', $post->getMorphClass())
                ->where('reactable_id', $post->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && Reaction::normalizeType((string) $existing->type) === $normalizedType) {
                $existing->delete();
                $post->decrementCounter('likes_count');
                $post->decrementCounter('reactions_count');
                $post->decrementCounter(Reaction::counterColumn($normalizedType));
                $this->postAuthor($post)?->decrementCounter('post_reactions_received_count');

                return ['action' => 'removed', 'current_reaction' => null];
            }

            if ($existing) {
                $post->decrementCounter(Reaction::counterColumn((string) $existing->type));
                $existing->update(['type' => $normalizedType]);
                $post->incrementCounter(Reaction::counterColumn($normalizedType));

                return ['action' => 'changed', 'current_reaction' => $normalizedType];
            }

            Reaction::query()->create([
                'user_id' => $user->id,
                'reactable_id' => $post->id,
                'reactable_type' => $post->getMorphClass(),
                'type' => $normalizedType,
            ]);

            $post->incrementCounter('likes_count');
            $post->incrementCounter('reactions_count');
            $post->incrementCounter(Reaction::counterColumn($normalizedType));
            $this->postAuthor($post)?->incrementCounter('post_reactions_received_count');

            return ['action' => 'added', 'current_reaction' => $normalizedType];
        });

        $freshPost = $post->fresh();
        $likesCount = (int) ($freshPost?->likes_count ?? $post->likes_count ?? 0);
        $reactionsCount = (int) ($freshPost?->reactions_count ?? $post->reactions_count ?? 0);

        if ($result['action'] === 'added' && $user->id !== $post->user_id) {
            $post->loadMissing('author');

            if ($post->author->notificationEnabled('post_likes')) {
                $notificationPost = $post->withoutRelation('author');
                $notificationUser = $this->relationLightReactor($user);

                $post->author->notify(new NewReaction($notificationUser, $notificationPost, $result['current_reaction']));
            }
        }

        return [
            'action' => $result['action'],
            'current_reaction' => $result['current_reaction'],
            'likes_count' => $likesCount,
            'reactions_count' => $reactionsCount,
            'reaction_counts' => $this->reactionCountsFromPost($freshPost ?? $post),
        ];
    }

    private function relationLightReactor(User $user): User
    {
        return $user->withoutRelation([
            'media',
            'followers',
            'following',
            'acceptedFollowers',
            'acceptedFollowing',
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function reactionCountsFromPost(Post $post): array
    {
        return Reaction::countMapForModel($post);
    }

    private function postAuthor(Post $post): ?User
    {
        return User::query()
            ->select(['id'])
            ->whereKey($post->getAttribute('user_id'))
            ->first();
    }
}
