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
        $state = $this->normalizeState($data, $contextType, $contextId);
        $identity = [
            'user_id' => $user->getKey(),
            'context_type' => $state['context_type'],
            'context_id' => $state['context_id'],
        ];
        $payload = [
            ...$identity,
            'body' => $this->normalizeNullableString($state['text_content'] ?? null),
            'visibility' => $state['selected_visibility'],
            'mood' => PostMood::normalize($state['selected_mood'] ?? null),
            'location' => $this->normalizeNullableString($state['location_display_text'] ?? null),
            'location_lat' => $state['location_lat'] ?? null,
            'location_lng' => $state['location_lng'] ?? null,
            'tagged_pets' => $this->encodeNullableArray($state['selected_pet_ids'] ?? []),
            'media_payload' => $this->encodeNullableArray($state['attachment_metadata'] ?? []),
            'link_preview' => $this->encodeNullableArray($state['link_preview'] ?? []),
            'state' => $this->encodeNullableArray($state),
            'scheduled_publish_at' => $state['scheduled_publish_at'] ?? null,
            'last_autosaved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        PostDraft::query()->upsert(
            [$payload],
            ['user_id'],
            [
                'context_type',
                'context_id',
                'body',
                'visibility',
                'mood',
                'location',
                'location_lat',
                'location_lng',
                'tagged_pets',
                'media_payload',
                'link_preview',
                'state',
                'scheduled_publish_at',
                'last_autosaved_at',
                'updated_at',
            ],
        );

        return $this->restore($user) ?? new PostDraft($payload);
    }

    public function restore(User $user, string $contextType = 'default', int $contextId = 0): ?PostDraft
    {
        return PostDraft::query()
            ->where('user_id', $user->getKey())
            ->latest('updated_at')
            ->first();
    }

    public function clear(User $user, string $contextType = 'default', int $contextId = 0): int
    {
        return (int) PostDraft::query()
            ->where('user_id', $user->getKey())
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function stateFor(PostDraft $draft): array
    {
        if (is_array($draft->state) && $draft->state !== []) {
            return $this->normalizeState($draft->state, (string) $draft->context_type, (int) $draft->context_id);
        }

        return $this->normalizeState([
            'body' => $draft->body,
            'visibility' => $draft->visibility,
            'mood' => $draft->mood,
            'location' => $draft->location,
            'location_lat' => $draft->location_lat,
            'location_lng' => $draft->location_lng,
            'tagged_pets' => is_array($draft->tagged_pets) ? $draft->tagged_pets : [],
            'media_payload' => is_array($draft->media_payload) ? $draft->media_payload : [],
            'link_preview' => is_array($draft->link_preview) ? $draft->link_preview : [],
            'scheduled_publish_at' => $draft->scheduled_publish_at?->toIso8601String(),
        ], (string) $draft->context_type, (int) $draft->context_id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeState(array $data, string $contextType, int $contextId): array
    {
        $attachmentMetadata = $this->normalizeArray($data['attachment_metadata'] ?? $data['media_payload'] ?? []);
        $temporaryFilePaths = $this->normalizeArray($data['temporary_file_paths'] ?? []);

        if ($temporaryFilePaths === [] && $attachmentMetadata !== []) {
            $temporaryFilePaths = collect($attachmentMetadata)
                ->pluck('temporary_path')
                ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
                ->values()
                ->all();
        }

        return [
            'text_content' => (string) ($data['text_content'] ?? $data['body'] ?? ''),
            'temporary_file_paths' => $temporaryFilePaths,
            'attachment_metadata' => $attachmentMetadata,
            'selected_pet_ids' => collect($data['selected_pet_ids'] ?? $data['tagged_pets'] ?? [])
                ->map(fn (mixed $petId): int => (int) $petId)
                ->filter(fn (int $petId): bool => $petId > 0)
                ->unique()
                ->values()
                ->all(),
            'location_display_text' => $this->normalizeNullableString($data['location_display_text'] ?? $data['location'] ?? null),
            'location_lat' => $data['location_lat'] ?? null,
            'location_lng' => $data['location_lng'] ?? null,
            'selected_mood' => PostMood::normalize($data['selected_mood'] ?? $data['mood'] ?? null),
            'selected_visibility' => $this->normalizeVisibility($data['selected_visibility'] ?? $data['visibility'] ?? null),
            'scheduled_publish_at' => $data['scheduled_publish_at'] ?? null,
            'scheduled_display_text' => $this->normalizeNullableString($data['scheduled_display_text'] ?? null),
            'scheduled_date' => $this->normalizeNullableString($data['scheduled_date'] ?? null),
            'scheduled_hour' => $this->normalizeNullableString($data['scheduled_hour'] ?? null),
            'scheduled_minute' => $this->normalizeNullableString($data['scheduled_minute'] ?? null),
            'link_preview' => $this->normalizeArray($data['link_preview'] ?? []),
            'detected_link_preview_url' => $this->normalizeNullableString($data['detected_link_preview_url'] ?? $data['link_preview_url'] ?? null),
            'context_type' => $contextType ?: 'default',
            'context_id' => max(0, $contextId),
        ];
    }

    private function normalizeVisibility(mixed $visibility): string
    {
        $normalized = trim((string) $visibility);

        return in_array($normalized, Post::visibilityValues(), true) ? $normalized : Post::VISIBILITY_PUBLIC;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function normalizeArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
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
