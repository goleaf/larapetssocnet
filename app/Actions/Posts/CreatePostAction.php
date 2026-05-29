<?php

namespace App\Actions\Posts;

use App\Enums\PostStatus;
use App\Events\PostCreated;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\ContentService;
use App\Services\PostDuplicateSubmissionGuard;
use App\Services\PostMentionService;
use App\Services\PostMetadataService;
use App\Support\Posts\PostMood;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreatePostAction
{
    public function __construct(
        private readonly ContentService $content,
        private readonly ProcessTagsAction $processTags,
        private readonly UploadMediaAction $uploadMedia,
        private readonly PostMetadataService $metadata,
        private readonly PostMentionService $mentions,
        private readonly PostDuplicateSubmissionGuard $duplicates,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): Post
    {
        $this->authorizePetAttachments($user, $data);
        $body = $this->content->plainText($data['body'] ?? null);
        $status = $this->normalizeStatus($data['status'] ?? PostStatus::Published);

        $create = function () use ($user, $data, $body, $status): Post {
            return DB::transaction(function () use ($user, $data, $body, $status): Post {
                $mediaFiles = $this->normalizeMediaFiles($data['media_files'] ?? []);
                $publishedAt = $this->resolvePublishedAt($status, $data['published_at'] ?? null);
                $scheduledPublishAt = $this->resolveScheduledPublishAt($status, $data['scheduled_publish_at'] ?? $data['published_at'] ?? null);
                $metadata = $this->metadata->normalize($data['metadata'] ?? null);
                $linkPreview = $this->metadata->linkPreview($body, $data['metadata'] ?? null);
                $location = $this->normalizeNullableString($data['location'] ?? null);

                $post = Post::query()->create([
                    'user_id' => $user->getKey(),
                    'author_type' => $user::class,
                    'author_id' => $user->getKey(),
                    'group_id' => $data['group_id'] ?? null,
                    'pet_id' => $data['pet_id'] ?? ($data['tagged_pets'][0] ?? null),
                    'body' => $body,
                    'body_html' => $body ? $this->content->process($body) : null,
                    'type' => $this->resolveType($mediaFiles),
                    'status' => $status->value,
                    'published_at' => $publishedAt,
                    'scheduled_publish_at' => $scheduledPublishAt,
                    'visibility' => $data['visibility'] ?? Post::VISIBILITY_PUBLIC,
                    'mood' => PostMood::normalize($data['mood'] ?? $metadata['mood'] ?? null),
                    'location' => $location,
                    'location_display_text' => $this->normalizeNullableString($data['location_display_text'] ?? $location),
                    'location_lat' => $data['location_lat'] ?? null,
                    'location_lng' => $data['location_lng'] ?? null,
                    'tagged_pets' => $data['tagged_pets'] ?? null,
                    'metadata' => $metadata,
                    'link_preview' => $linkPreview,
                    'is_system_generated' => (bool) ($data['is_system_generated'] ?? false),
                    'system_source' => $this->normalizeNullableString($data['system_source'] ?? null),
                    'original_post_id' => $data['original_post_id'] ?? null,
                    'quote_post_id' => $data['quote_post_id'] ?? null,
                ]);

                $this->processTags->handle($post);
                $this->mentions->sync($post, $user);

                if ($mediaFiles !== []) {
                    $this->uploadMedia->handle($post, $mediaFiles);
                }

                $this->syncTaggedPets($post, $data);

                DB::afterCommit(static function () use ($post): void {
                    PostCreated::dispatch($post);
                });

                return $post;
            });
        };

        if ($status !== PostStatus::Draft) {
            return $this->duplicates->run($user, $body, $create);
        }

        return $create();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncTaggedPets(Post $post, array $data): void
    {
        $petIds = collect([$data['pet_id'] ?? null])
            ->merge($data['tagged_pets'] ?? [])
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($petIds->isEmpty() || ! method_exists($post, 'pets')) {
            return;
        }

        $primaryPetId = (int) ($data['pet_id'] ?? $petIds->first());
        $post->pets()->sync($petIds->mapWithKeys(
            fn (int $petId): array => [$petId => ['is_primary' => $petId === $primaryPetId]]
        )->all());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function authorizePetAttachments(User $user, array $data): void
    {
        $petIds = collect([$data['pet_id'] ?? null])
            ->merge($data['tagged_pets'] ?? [])
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($petIds->isEmpty()) {
            return;
        }

        /** @var EloquentCollection<int, Pet> $pets */
        $pets = Pet::query()
            ->whereIn('id', $petIds->all())
            ->select(['id', 'user_id', 'species', 'breed'])
            ->get();

        foreach ($pets as $pet) {
            Gate::forUser($user)->authorize('createPostForPet', $pet);
        }
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function normalizeMediaFiles(mixed $mediaFiles): array
    {
        if ($mediaFiles instanceof UploadedFile) {
            return [$mediaFiles];
        }

        if (! is_array($mediaFiles)) {
            return [];
        }

        return collect($mediaFiles)
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, UploadedFile>  $mediaFiles
     */
    private function resolveType(array $mediaFiles): string
    {
        if ($mediaFiles === []) {
            return Post::TYPE_TEXT;
        }

        foreach ($mediaFiles as $mediaFile) {
            if (str_starts_with((string) $mediaFile->getMimeType(), 'video/')) {
                return Post::TYPE_VIDEO;
            }
        }

        return Post::TYPE_PHOTO;
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
}
