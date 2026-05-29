<?php

namespace App\Actions\Posts;

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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdatePostAction
{
    public function __construct(
        private readonly ContentService $content,
        private readonly ProcessTagsAction $processTags,
        private readonly PostMetadataService $metadata,
        private readonly PostMentionService $mentions,
        private readonly PostContentHasher $hasher,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Post $post, array $data): Post
    {
        $this->authorizeActor($actor, $post);
        $this->ensureWithinEditWindow($post);
        Gate::forUser($actor)->authorize('update', $post);

        return DB::transaction(function () use ($actor, $post, $data): Post {
            $currentBody = $this->normalizeNullableString($post->getAttribute('body'));
            $currentMetadata = $post->getAttribute('metadata');
            $currentPublishedAt = $post->getAttribute('published_at');
            $currentVisibility = $post->getAttribute('visibility') ?? Post::VISIBILITY_PUBLIC;
            $currentLocation = $post->getAttribute('location');
            $currentLocationDisplayText = $post->getAttribute('location_display_text');
            $currentLocationLat = $post->getAttribute('location_lat');
            $currentLocationLng = $post->getAttribute('location_lng');
            $currentLinkPreview = $post->getAttribute('link_preview');
            $currentPetIds = $this->currentPetIds($post);

            $nextBody = array_key_exists('body', $data)
                ? $this->content->plainText($data['body'])
                : $currentBody;

            $nextMetadata = array_key_exists('metadata', $data)
                ? $this->metadata->normalize(is_array($data['metadata']) ? $data['metadata'] : null)
                : $currentMetadata;
            $nextLinkPreview = array_key_exists('link_preview', $data)
                ? (is_array($data['link_preview']) ? $data['link_preview'] : null)
                : (
                    array_key_exists('metadata', $data) || array_key_exists('body', $data)
                        ? $this->metadata->linkPreview($nextBody, is_array($data['metadata'] ?? null) ? $data['metadata'] : null)
                        : $currentLinkPreview
                );

            $nextStatus = $this->normalizeStatus($data['status'] ?? $post->getAttribute('status') ?? PostStatus::Published);
            $nextPublishedAt = $currentPublishedAt;
            $nextScheduledPublishAt = $post->getAttribute('scheduled_publish_at');

            if (array_key_exists('status', $data) || array_key_exists('published_at', $data) || array_key_exists('scheduled_publish_at', $data)) {
                $nextPublishedAt = $this->resolvePublishedAt($nextStatus, $data['scheduled_publish_at'] ?? $data['published_at'] ?? $currentPublishedAt);
                $nextScheduledPublishAt = $this->resolveScheduledPublishAt($nextStatus, $data['scheduled_publish_at'] ?? $data['published_at'] ?? $nextScheduledPublishAt);
            }

            $nextPetIds = $this->nextPetIds($data, $post);
            $nextPrimaryPetId = $nextPetIds[0] ?? null;
            $nextVisibility = $data['visibility'] ?? $currentVisibility;
            $nextMood = PostMood::normalize($data['mood'] ?? $nextMetadata['mood'] ?? $post->getAttribute('mood'));
            $nextLocation = $this->normalizeNullableString($data['location'] ?? $currentLocation);
            $nextLocationDisplayText = $this->normalizeNullableString($data['location_display_text'] ?? $data['location'] ?? $currentLocationDisplayText);
            $nextLocationLat = array_key_exists('location_lat', $data) ? $this->normalizeNullableString($data['location_lat']) : $currentLocationLat;
            $nextLocationLng = array_key_exists('location_lng', $data) ? $this->normalizeNullableString($data['location_lng']) : $currentLocationLng;
            $editCount = (int) ($post->getAttribute('edit_count') ?? 0);

            if ($this->hasEditableChanges(
                currentBody: $currentBody,
                nextBody: $nextBody,
                currentVisibility: $currentVisibility,
                nextVisibility: $nextVisibility,
                currentMood: PostMood::normalize($post->getAttribute('mood')),
                nextMood: $nextMood,
                currentLocation: $currentLocation,
                nextLocation: $nextLocation,
                currentLocationDisplayText: $currentLocationDisplayText,
                nextLocationDisplayText: $nextLocationDisplayText,
                currentLocationLat: $currentLocationLat,
                nextLocationLat: $nextLocationLat,
                currentLocationLng: $currentLocationLng,
                nextLocationLng: $nextLocationLng,
                currentPetIds: $currentPetIds,
                nextPetIds: $nextPetIds,
                currentLinkPreview: $currentLinkPreview,
                nextLinkPreview: $nextLinkPreview,
            )) {
                $post->setAttribute('edited_at', now());
                $editCount++;
            }

            $post->update([
                'body' => $nextBody,
                'content_hash' => $this->hasher->hash($nextBody),
                'body_html' => $nextBody ? $this->content->process($nextBody) : null,
                'visibility' => $nextVisibility,
                'mood' => $nextMood,
                'location' => $nextLocation,
                'location_display_text' => $nextLocationDisplayText,
                'location_lat' => $nextLocationLat,
                'location_lng' => $nextLocationLng,
                'pet_id' => $nextPrimaryPetId,
                'tagged_pets' => $nextPetIds === [] ? null : $nextPetIds,
                'status' => $nextStatus->value,
                'published_at' => $nextPublishedAt,
                'scheduled_publish_at' => $nextScheduledPublishAt,
                'metadata' => $nextMetadata,
                'link_preview' => $nextLinkPreview,
                'edited_at' => $post->getAttribute('edited_at'),
                'edit_count' => $editCount,
            ]);

            $this->processTags->handle($post);
            $this->mentions->sync($post, $actor);

            $this->syncPetAttachments($actor, $post, $currentPetIds, $nextPetIds);

            return $post->fresh() ?? $post;
        });
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeActor(User $actor, Post $post): void
    {
        if ((int) $actor->getKey() === (int) $post->getAttribute('user_id')) {
            return;
        }

        Gate::forUser($actor)->authorize('update', $post);
    }

    /**
     * @throws ValidationException
     */
    private function ensureWithinEditWindow(Post $post): void
    {
        if ($post->created_at === null || $post->created_at->greaterThanOrEqualTo(now()->subDay())) {
            return;
        }

        throw ValidationException::withMessages([
            'edit' => 'Posts can only be edited within 24 hours of creation.',
        ]);
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
     * @return list<int>
     */
    private function currentPetIds(Post $post): array
    {
        $relationshipPetIds = $post->pets()
            ->pluck('pets.id')
            ->map(static fn (mixed $petId): int => (int) $petId);

        $storedPetIds = collect([$post->getAttribute('pet_id')])
            ->merge(is_array($post->getAttribute('tagged_pets')) ? $post->getAttribute('tagged_pets') : [])
            ->map(static fn (mixed $petId): int => (int) $petId);

        return $relationshipPetIds
            ->merge($storedPetIds)
            ->filter(static fn (int $petId): bool => $petId > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function nextPetIds(array $data, Post $post): array
    {
        if (! array_key_exists('pet_id', $data) && ! array_key_exists('tagged_pets', $data)) {
            return $this->currentPetIds($post);
        }

        return collect([$data['pet_id'] ?? null])
            ->merge(is_array($data['tagged_pets'] ?? null) ? $data['tagged_pets'] : [])
            ->map(static fn (mixed $petId): int => (int) $petId)
            ->filter(static fn (int $petId): bool => $petId > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $currentPetIds
     * @param  list<int>  $nextPetIds
     */
    private function syncPetAttachments(User $actor, Post $post, array $currentPetIds, array $nextPetIds): void
    {
        $pets = $this->editablePets($nextPetIds);

        if ($nextPetIds !== [] && $pets->count() !== count($nextPetIds)) {
            throw ValidationException::withMessages([
                'tagged_pets' => 'One or more tagged pets could not be found.',
            ]);
        }

        foreach ($pets as $pet) {
            Gate::forUser($actor)->authorize('createPostForPet', $pet);
        }

        $primaryPetId = $nextPetIds[0] ?? null;

        $post->pets()->sync(collect($nextPetIds)->mapWithKeys(
            fn (int $petId): array => [$petId => ['is_primary' => $petId === $primaryPetId]]
        )->all());

        $attachedPetIds = array_values(array_diff($nextPetIds, $currentPetIds));
        $detachedPetIds = array_values(array_diff($currentPetIds, $nextPetIds));

        if ($attachedPetIds !== []) {
            Pet::query()
                ->whereIn('id', $attachedPetIds)
                ->increment('posts_count');
        }

        if ($detachedPetIds !== []) {
            Pet::query()
                ->whereIn('id', $detachedPetIds)
                ->where('posts_count', '>', 0)
                ->decrement('posts_count');
        }
    }

    /**
     * @param  list<int>  $petIds
     * @return EloquentCollection<int, Pet>
     */
    private function editablePets(array $petIds): EloquentCollection
    {
        if ($petIds === []) {
            return Pet::query()->whereKey(-1)->get();
        }

        return Pet::query()
            ->whereIn('id', $petIds)
            ->get();
    }

    /**
     * @param  list<int>  $currentPetIds
     * @param  list<int>  $nextPetIds
     * @param  array<string, mixed>|null  $currentLinkPreview
     * @param  array<string, mixed>|null  $nextLinkPreview
     */
    private function hasEditableChanges(
        ?string $currentBody,
        ?string $nextBody,
        mixed $currentVisibility,
        mixed $nextVisibility,
        ?string $currentMood,
        ?string $nextMood,
        mixed $currentLocation,
        ?string $nextLocation,
        mixed $currentLocationDisplayText,
        ?string $nextLocationDisplayText,
        mixed $currentLocationLat,
        mixed $nextLocationLat,
        mixed $currentLocationLng,
        mixed $nextLocationLng,
        array $currentPetIds,
        array $nextPetIds,
        mixed $currentLinkPreview,
        mixed $nextLinkPreview,
    ): bool {
        return $currentBody !== $nextBody
            || (string) $currentVisibility !== (string) $nextVisibility
            || $currentMood !== $nextMood
            || $this->normalizeNullableString($currentLocation) !== $nextLocation
            || $this->normalizeNullableString($currentLocationDisplayText) !== $nextLocationDisplayText
            || $this->normalizeNullableString($currentLocationLat) !== $this->normalizeNullableString($nextLocationLat)
            || $this->normalizeNullableString($currentLocationLng) !== $this->normalizeNullableString($nextLocationLng)
            || $currentPetIds !== $nextPetIds
            || $currentLinkPreview !== $nextLinkPreview;
    }
}
