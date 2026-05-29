<?php

namespace App\Actions\Posts;

use App\Actions\Pets\AttachPetToPostAction;
use App\Actions\Pets\DetachPetFromPostAction;
use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\ContentService;
use App\Services\PostMentionService;
use App\Services\PostMetadataService;
use App\Support\Posts\PostContentHasher;
use App\Support\Posts\PostMood;
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
        private readonly PostMentionService $mentions,
        private readonly PostContentHasher $hasher,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Post $post, array $data): Post
    {
        return DB::transaction(function () use ($actor, $post, $data): Post {
            $currentBody = $this->normalizeNullableString($post->getAttribute('body'));
            $currentMetadata = $post->getAttribute('metadata');
            $currentPublishedAt = $post->getAttribute('published_at');
            $currentEditedAt = $post->getAttribute('edited_at');
            $currentVisibility = $post->getAttribute('visibility') ?? Post::VISIBILITY_PUBLIC;
            $currentLocation = $post->getAttribute('location');
            $currentTaggedPets = $post->getAttribute('tagged_pets');

            $nextBody = array_key_exists('body', $data)
                ? $this->content->plainText($data['body'])
                : $currentBody;

            $nextMetadata = array_key_exists('metadata', $data)
                ? $this->metadata->normalize(is_array($data['metadata']) ? $data['metadata'] : null)
                : $currentMetadata;
            $nextLinkPreview = array_key_exists('metadata', $data) || array_key_exists('body', $data)
                ? $this->metadata->linkPreview($nextBody, is_array($data['metadata'] ?? null) ? $data['metadata'] : null)
                : $post->getAttribute('link_preview');

            $nextStatus = $this->normalizeStatus($data['status'] ?? $post->getAttribute('status') ?? PostStatus::Published);
            $nextPublishedAt = $currentPublishedAt;
            $nextScheduledPublishAt = $post->getAttribute('scheduled_publish_at');

            if (array_key_exists('status', $data) || array_key_exists('published_at', $data) || array_key_exists('scheduled_publish_at', $data)) {
                $nextPublishedAt = $this->resolvePublishedAt($nextStatus, $data['scheduled_publish_at'] ?? $data['published_at'] ?? $currentPublishedAt);
                $nextScheduledPublishAt = $this->resolveScheduledPublishAt($nextStatus, $data['scheduled_publish_at'] ?? $data['published_at'] ?? $nextScheduledPublishAt);
            }

            $editedAt = $currentEditedAt;
            $editCount = (int) ($post->getAttribute('edit_count') ?? 0);

            if (array_key_exists('body', $data) && $nextBody !== $currentBody) {
                $editedAt = now();
                $editCount++;
            }

            $post->update([
                'body' => $nextBody,
                'content_hash' => $this->hasher->hash($nextBody),
                'body_html' => $nextBody ? $this->content->process($nextBody) : null,
                'visibility' => $data['visibility'] ?? $currentVisibility,
                'mood' => PostMood::normalize($data['mood'] ?? $nextMetadata['mood'] ?? $post->getAttribute('mood')),
                'location' => $this->normalizeNullableString($data['location'] ?? $currentLocation),
                'location_display_text' => $this->normalizeNullableString($data['location_display_text'] ?? $data['location'] ?? $post->getAttribute('location_display_text')),
                'tagged_pets' => $data['tagged_pets'] ?? $currentTaggedPets,
                'status' => $nextStatus->value,
                'published_at' => $nextPublishedAt,
                'scheduled_publish_at' => $nextScheduledPublishAt,
                'metadata' => $nextMetadata,
                'link_preview' => $nextLinkPreview,
                'edited_at' => $editedAt,
                'edit_count' => $editCount,
            ]);

            $this->processTags->handle($post);
            $this->mentions->sync($post, $actor);

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
        if ($status->clearsPublishedAt()) {
            return null;
        }

        if ($publishedAt instanceof CarbonInterface) {
            return $publishedAt;
        }

        if (is_string($publishedAt) && $publishedAt !== '') {
            return CarbonImmutable::parse($publishedAt);
        }

        if (! $status->isPubliclyReachable()) {
            return null;
        }

        return now();
    }

    private function resolveScheduledPublishAt(PostStatus $status, mixed $publishedAt): ?CarbonInterface
    {
        if ($status !== PostStatus::Scheduled) {
            return null;
        }

        if ($publishedAt instanceof CarbonInterface) {
            return $publishedAt;
        }

        if (is_string($publishedAt) && $publishedAt !== '') {
            return CarbonImmutable::parse($publishedAt);
        }

        return null;
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
            $currentPetId = $post->getAttribute('pet_id') ? (int) $post->getAttribute('pet_id') : null;

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
