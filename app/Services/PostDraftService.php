<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Content\PostDraft;
use App\Models\Identity\User;
use App\Support\Posts\PostMood;
use Illuminate\Support\Carbon;

class PostDraftService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function autosave(User $user, array $data, string $contextType = 'default', int $contextId = 0): PostDraft
    {
        $now = Carbon::now();
        $identity = [
            'user_id' => $user->getKey(),
            'context_type' => $contextType ?: 'default',
            'context_id' => max(0, $contextId),
        ];
        $payload = [
            ...$identity,
            'body' => $this->normalizeNullableString($data['body'] ?? null),
            'visibility' => $data['visibility'] ?? Post::VISIBILITY_PUBLIC,
            'mood' => PostMood::normalize($data['mood'] ?? null),
            'location' => $this->normalizeNullableString($data['location'] ?? null),
            'location_lat' => $data['location_lat'] ?? null,
            'location_lng' => $data['location_lng'] ?? null,
            'tagged_pets' => $this->encodeNullableArray($data['tagged_pets'] ?? []),
            'media_payload' => $this->encodeNullableArray($data['media_payload'] ?? []),
            'link_preview' => $this->encodeNullableArray($data['link_preview'] ?? null),
            'scheduled_publish_at' => $data['scheduled_publish_at'] ?? null,
            'last_autosaved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        PostDraft::query()->upsert(
            [$payload],
            ['user_id', 'context_type', 'context_id'],
            [
                'body',
                'visibility',
                'mood',
                'location',
                'location_lat',
                'location_lng',
                'tagged_pets',
                'media_payload',
                'link_preview',
                'scheduled_publish_at',
                'last_autosaved_at',
                'updated_at',
            ],
        );

        return $this->restore($user, $contextType, $contextId) ?? new PostDraft($payload);
    }

    public function restore(User $user, string $contextType = 'default', int $contextId = 0): ?PostDraft
    {
        return PostDraft::query()
            ->where('user_id', $user->getKey())
            ->where('context_type', $contextType ?: 'default')
            ->where('context_id', max(0, $contextId))
            ->first();
    }

    public function clear(User $user, string $contextType = 'default', int $contextId = 0): int
    {
        return (int) PostDraft::query()
            ->where('user_id', $user->getKey())
            ->where('context_type', $contextType ?: 'default')
            ->where('context_id', max(0, $contextId))
            ->delete();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function encodeNullableArray(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value) || $value === []) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
