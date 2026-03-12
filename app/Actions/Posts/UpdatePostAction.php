<?php

namespace App\Actions\Posts;

use App\Actions\Pets\AttachPetToPostAction;
use App\Actions\Pets\DetachPetFromPostAction;
use App\Enums\PostStatus;
use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use App\Services\ContentService;
use App\Services\PostMetadataService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class UpdatePostAction
{
    public function __construct(
        private readonly ContentService $content,
        private readonly ProcessTagsAction $processTags,
        private readonly AttachPetToPostAction $attachPetToPostAction,
        private readonly DetachPetFromPostAction $detachPetFromPostAction,
        private readonly PostMetadataService $metadata,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Post $post, array $data): Post
    {
        return DB::transaction(function () use ($actor, $post, $data): Post {
            $nextBody = array_key_exists('body', $data)
                ? $this->normalizeNullableString($data['body'])
                : $post->body;

            $nextMetadata = array_key_exists('metadata', $data)
                ? $this->metadata->normalize(is_array($data['metadata']) ? $data['metadata'] : null)
                : $post->metadata;

            $nextStatus = $this->normalizeStatus($data['status'] ?? $post->status ?? PostStatus::Published);
            $nextPublishedAt = $post->published_at;

            if (array_key_exists('status', $data) || array_key_exists('published_at', $data)) {
                $nextPublishedAt = $this->resolvePublishedAt($nextStatus, $data['published_at'] ?? $post->published_at);
            }

            $editedAt = $post->edited_at;

            if (array_key_exists('body', $data) && $nextBody !== $post->body) {
                $editedAt = now();
            }

            $post->update([
                'body' => $nextBody,
                'body_html' => $nextBody ? $this->content->process($nextBody) : null,
                'visibility' => $data['visibility'] ?? $post->visibility,
                'location' => $this->normalizeNullableString($data['location'] ?? $post->location),
                'tagged_pets' => $data['tagged_pets'] ?? $post->tagged_pets,
                'status' => $nextStatus->value,
                'published_at' => $nextPublishedAt,
                'metadata' => $nextMetadata,
                'edited_at' => $editedAt,
            ]);

            $this->processTags->handle($post);

            $this->syncPetAttachment($actor, $post, $data);

            return $post->fresh() ?? $post;
        });
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeStatus(mixed $status): PostStatus
    {
        if ($status instanceof PostStatus) {
            return $status;
        }

        if (is_string($status)) {
            $parsed = PostStatus::tryFrom($status);

            if ($parsed) {
                return $parsed;
            }
        }

        return PostStatus::Published;
    }

    private function resolvePublishedAt(PostStatus $status, mixed $publishedAt): ?CarbonInterface
    {
        if ($status === PostStatus::Draft) {
            return null;
        }

        if ($publishedAt instanceof CarbonInterface) {
            return $publishedAt;
        }

        if (is_string($publishedAt) && $publishedAt !== '') {
            return CarbonImmutable::parse($publishedAt);
        }

        if ($status === PostStatus::Scheduled || $status === PostStatus::Archived) {
            return $publishedAt instanceof CarbonInterface ? $publishedAt : null;
        }

        return now();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPetAttachment(User $actor, Post $post, array $data): void
    {
        if (! array_key_exists('pet_id', $data) && ! array_key_exists('tagged_pets', $data)) {
            return;
        }

        $nextPetId = $data['pet_id'] ?? ($data['tagged_pets'][0] ?? null);
        $nextPetId = $nextPetId ? (int) $nextPetId : null;

        if ($nextPetId === null) {
            $currentPetId = $post->pet_id ? (int) $post->pet_id : null;

            if (! $currentPetId) {
                return;
            }

            $currentPet = Pet::query()->whereKey($currentPetId)->first();

            if ($currentPet) {
                $this->detachPetFromPostAction->handle($actor, $post, $currentPet);
            }

            return;
        }

        $pet = Pet::query()->whereKey($nextPetId)->first();

        if (! $pet) {
            return;
        }

        $this->attachPetToPostAction->handle($actor, $post, $pet);
    }
}
