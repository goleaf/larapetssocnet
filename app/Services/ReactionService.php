<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostReaction;
use App\Models\Reaction;
use App\Models\User;
use App\Notifications\NewReaction;
use Illuminate\Validation\ValidationException;

class ReactionService
{
    /**
     * @var list<string>
     */
    public const TYPES = ['love', 'cute', 'funny', 'wow', 'sad', 'support'];

    /**
     * @return array{action: 'added'|'changed'|'removed', current_reaction: ?string, likes_count: int}
     */
    public function react(User $user, Post $post, string $type): array
    {
        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Invalid reaction type.']);
        }

        $existing = PostReaction::query()
            ->where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing?->type === $type) {
            $existing->delete();
            $this->syncLegacyReaction($user, $post, null);
            $likesCount = max(0, (int) $post->reactions()->count());
            $post->update(['likes_count' => $likesCount]);

            return ['action' => 'removed', 'current_reaction' => null, 'likes_count' => $likesCount];
        }

        if ($existing) {
            $existing->update(['type' => $type]);
            $this->syncLegacyReaction($user, $post, $type);
            $likesCount = max(0, (int) $post->reactions()->count());
            $post->update(['likes_count' => $likesCount]);

            return ['action' => 'changed', 'current_reaction' => $type, 'likes_count' => $likesCount];
        }

        PostReaction::query()->create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'type' => $type,
        ]);
        $this->syncLegacyReaction($user, $post, $type);

        $likesCount = max(0, (int) $post->reactions()->count());
        $post->update(['likes_count' => $likesCount]);

        if ($user->id !== $post->user_id) {
            if ($post->author->notificationEnabled('post_likes')) {
                $notificationPost = $post->withoutRelation('author');
                $notificationUser = $this->relationLightReactor($user);

                $post->author->notify(new NewReaction($notificationUser, $notificationPost, $type));
            }
        }

        return ['action' => 'added', 'current_reaction' => $type, 'likes_count' => $likesCount];
    }

    private function syncLegacyReaction(User $user, Post $post, ?string $type): void
    {
        $existing = Reaction::query()
            ->where('user_id', $user->id)
            ->where('reactable_id', $post->id)
            ->where('reactable_type', Post::class)
            ->first();

        if ($type === null) {
            $existing?->delete();

            return;
        }

        if ($existing) {
            $existing->update(['type' => $type]);

            return;
        }

        Reaction::query()->create([
            'user_id' => $user->id,
            'reactable_id' => $post->id,
            'reactable_type' => Post::class,
            'type' => $type,
        ]);
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
}
