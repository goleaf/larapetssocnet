<?php

namespace App\Services;

use App\Models\Content\CommentDraft;
use App\Models\Content\Post;
use App\Models\Identity\User;

class CommentDraftService
{
    /**
     * @param  array{gif_url?: string|null, gif_preview_url?: string|null, gif_title?: string|null, gif_provider?: string|null}|null  $gif
     */
    public function save(User $user, Post $post, string $body, ?array $gif = null): ?CommentDraft
    {
        $body = trim($body);
        $gif = $this->normalizeGif($gif);

        if ($body === '' && $gif === null) {
            $this->discard($user, $post);

            return null;
        }

        $now = now();

        CommentDraft::query()->upsert([
            [
                'user_id' => $user->getKey(),
                'post_id' => $post->getKey(),
                'body' => $body,
                'gif_url' => $gif['gif_url'] ?? null,
                'gif_preview_url' => $gif['gif_preview_url'] ?? null,
                'gif_title' => $gif['gif_title'] ?? null,
                'gif_provider' => $gif['gif_provider'] ?? null,
                'last_autosaved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['user_id', 'post_id'], [
            'body',
            'gif_url',
            'gif_preview_url',
            'gif_title',
            'gif_provider',
            'last_autosaved_at',
            'updated_at',
        ]);

        return $this->restore($user, $post);
    }

    public function restore(User $user, Post $post): ?CommentDraft
    {
        return CommentDraft::query()
            ->where('user_id', $user->getKey())
            ->where('post_id', $post->getKey())
            ->first();
    }

    public function discard(User $user, Post $post): void
    {
        CommentDraft::query()
            ->where('user_id', $user->getKey())
            ->where('post_id', $post->getKey())
            ->delete();
    }

    /**
     * @param  array{gif_url?: string|null, gif_preview_url?: string|null, gif_title?: string|null, gif_provider?: string|null}|null  $gif
     * @return array{gif_url: string, gif_preview_url: string|null, gif_title: string|null, gif_provider: string|null}|null
     */
    private function normalizeGif(?array $gif): ?array
    {
        $url = trim((string) ($gif['gif_url'] ?? ''));

        if ($url === '') {
            return null;
        }

        return [
            'gif_url' => $url,
            'gif_preview_url' => filled($gif['gif_preview_url'] ?? null) ? (string) $gif['gif_preview_url'] : null,
            'gif_title' => filled($gif['gif_title'] ?? null) ? (string) $gif['gif_title'] : null,
            'gif_provider' => filled($gif['gif_provider'] ?? null) ? (string) $gif['gif_provider'] : null,
        ];
    }
}
