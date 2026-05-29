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
     * @var list<string>
     */
    public const TYPES = [
        Reaction::TYPE_LOVE,
        Reaction::TYPE_CUTE,
        Reaction::TYPE_FUNNY,
        Reaction::TYPE_WOW,
        Reaction::TYPE_SAD,
        Reaction::TYPE_SUPPORT,
    ];

    /**
     * @return array{action: 'added'|'changed'|'removed', current_reaction: ?string, likes_count: int}
     */
    public function react(User $user, Post $post, string $type): array
    {
        $normalizedType = Reaction::normalizeType($type);

        if (! in_array($normalizedType, self::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Invalid reaction type.']);
        }

        $result = DB::transaction(function () use ($user, $post, $normalizedType): array {
            $existing = Reaction::query()
                ->where('reactable_type', $post->getMorphClass())
                ->where('reactable_id', $post->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing?->type === $normalizedType) {
                $existing->delete();
                $post->decrementCounter('likes_count');
                $post->decrementCounter('reactions_count');
                $post->decrementCounter($this->counterColumn($normalizedType));
                $this->postAuthor($post)?->decrementCounter('post_reactions_received_count');

                return ['action' => 'removed', 'current_reaction' => null];
            }

            if ($existing) {
                $post->decrementCounter($this->counterColumn(Reaction::normalizeType((string) $existing->type)));
                $existing->update(['type' => $normalizedType]);
                $post->incrementCounter($this->counterColumn($normalizedType));

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
            $post->incrementCounter($this->counterColumn($normalizedType));
            $this->postAuthor($post)?->incrementCounter('post_reactions_received_count');

            return ['action' => 'added', 'current_reaction' => $normalizedType];
        });

        $likesCount = (int) ($post->fresh()?->likes_count ?? $post->likes_count ?? 0);

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

    private function counterColumn(string $type): string
    {
        return $type.'_count';
    }

    private function postAuthor(Post $post): ?User
    {
        return User::query()
            ->select(['id'])
            ->whereKey($post->getAttribute('user_id'))
            ->first();
    }
}
