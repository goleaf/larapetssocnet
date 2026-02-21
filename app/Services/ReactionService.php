<?php

namespace App\Services;

use App\Models\Post;
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

        $existing = $user->reactions()
            ->where('reactable_id', $post->id)
            ->where('reactable_type', Post::class)
            ->first();

        if ($existing?->type === $type) {
            $existing->delete();
            $likesCount = max(0, (int) $post->reactions()->count());
            $post->update(['likes_count' => $likesCount]);

            return ['action' => 'removed', 'current_reaction' => null, 'likes_count' => $likesCount];
        }

        if ($existing) {
            $existing->update(['type' => $type]);
            $likesCount = max(0, (int) $post->reactions()->count());
            $post->update(['likes_count' => $likesCount]);

            return ['action' => 'changed', 'current_reaction' => $type, 'likes_count' => $likesCount];
        }

        Reaction::query()->create([
            'user_id' => $user->id,
            'reactable_id' => $post->id,
            'reactable_type' => Post::class,
            'type' => $type,
        ]);

        $likesCount = max(0, (int) $post->reactions()->count());
        $post->update(['likes_count' => $likesCount]);

        if ($user->id !== $post->user_id) {
            $post->author->notify(new NewReaction($user, $post, $type));
        }

        return ['action' => 'added', 'current_reaction' => $type, 'likes_count' => $likesCount];
    }
}
