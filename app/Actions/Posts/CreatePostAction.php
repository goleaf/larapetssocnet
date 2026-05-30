<?php

namespace App\Actions\Posts;

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\ContentService;
use App\Services\FeedFanOutService;
use App\Services\PostDuplicateSubmissionGuard;
use App\Services\PostLinkPreviewService;
use App\Services\PostMediaProcessingService;
use App\Services\PostMentionService;
use App\Services\PostMetadataService;
use App\Support\Posts\PostCreationInput;
use App\Support\Posts\PostCreationResult;
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
        private readonly PostMediaProcessingService $mediaProcessing,
        private readonly FeedFanOutService $feedFanOut,
        private readonly PostLinkPreviewService $linkPreviews,
    ) {}

    public function handle(User $user, PostCreationInput $input): PostCreationResult
    {
        $data = $input->toArray();

        $this->authorizePetAttachments($user, $data);

        $body = $this->content->plainText($data['body'] ?? null);
        $contentHash = $this->duplicates->hash($body);
        $skipDuplicateCheck = (bool) ($data['confirmed_duplicate'] ?? $data['skip_duplicate_check'] ?? false);

        if (! $skipDuplicateCheck && $contentHash !== null) {
            $duplicate = $this->duplicates->recentDuplicate($user, $body);

            if ($duplicate instanceof Post) {
                return PostCreationResult::duplicate($duplicate, $contentHash);
            }
        }

        $mediaProcessingTasks = [];
        $fanOutPostId = null;
        $linkPreviewTask = null;

        $result = DB::transaction(function () use ($user, $data, $body, $contentHash, &$mediaProcessingTasks, &$fanOutPostId, &$linkPreviewTask): PostCreationResult {
            $mediaFiles = $this->normalizeMediaFiles($data['media_files'] ?? []);
            $temporaryMedia = $this->normalizeTemporaryMedia($data['media_attachments'] ?? []);
            $scheduledPublishAt = $this->resolveScheduledPublishAt($data['scheduled_publish_at'] ?? null);
            $status = $this->resolveCreationStatus($data['status'] ?? null, $scheduledPublishAt);
            $publishedAt = $this->resolvePublishedAt($status, $data['published_at'] ?? null);
            $metadata = $this->metadata->normalize($data['metadata'] ?? null);
            $linkPreview = is_array($data['link_preview'] ?? null)
                ? $data['link_preview']
                : $this->metadata->linkPreview(null, $metadata);
            $linkPreviewUrl = $this->resolveLinkPreviewUrl($data, $body, is_array($linkPreview) ? $linkPreview : null);
            $location = $this->normalizeNullableString($data['location'] ?? null);

            $post = Post::query()->create([
                'user_id' => $user->getKey(),
                'author_type' => $user::class,
                'author_id' => $user->getKey(),
                'group_id' => $data['group_id'] ?? null,
                'pet_id' => $data['pet_id'] ?? ($data['tagged_pets'][0] ?? null),
                'body' => $body,
                'content_hash' => $contentHash,
                'body_html' => $body ? $this->content->process($body) : null,
                'type' => $this->resolveType($mediaFiles, $temporaryMedia),
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

            if ($mediaFiles !== []) {
                $this->uploadMedia->handle($post, $mediaFiles);
            }

            foreach ($temporaryMedia as $media) {
                $postMedia = $post->postMedia()->create([
                    'file_path' => $media['temporary_path'],
                    'media_type' => $media['media_type'],
                    'alt_text' => $media['alt_text'],
                    'processing_status' => 'processing',
                    'order' => $media['order'],
                ]);

                $mediaProcessingTasks[] = [
                    'temporary_path' => $media['temporary_path'],
                    'post_id' => (int) $post->getKey(),
                    'post_media_id' => (int) $postMedia->getKey(),
                    'media_type' => $media['media_type'],
                    'alt_text' => $media['alt_text'],
                    'order' => $media['order'],
                ];
            }

            $this->syncTaggedPets($post, $data);
            $this->processTags->handle($post);
            $this->mentions->sync($post, $user, $status->isPubliclyReachable());

            if ($status->isPubliclyReachable()) {
                $fanOutPostId = (int) $post->getKey();
            }

            if ($linkPreview === null && $linkPreviewUrl !== null && $status !== PostStatus::Draft) {
                $linkPreviewTask = [
                    'url' => $linkPreviewUrl,
                    'post_id' => (int) $post->getKey(),
                ];
            }

            return PostCreationResult::created($post, $contentHash);
        });

        foreach ($mediaProcessingTasks as $task) {
            $this->mediaProcessing->process(
                temporaryPath: $task['temporary_path'],
                postId: $task['post_id'],
                mediaType: $task['media_type'],
                altText: $task['alt_text'],
                order: $task['order'],
                postMediaId: $task['post_media_id'],
            );
        }

        if ($fanOutPostId !== null) {
            $this->feedFanOut->fanOutPost($fanOutPostId);
        }

        if ($linkPreviewTask !== null) {
            $this->linkPreviews->fetch(
                url: $linkPreviewTask['url'],
                postId: $linkPreviewTask['post_id'],
            );
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $linkPreview
     */
    private function resolveLinkPreviewUrl(array $data, ?string $body, ?array $linkPreview): ?string
    {
        foreach ([
            $data['link_preview_url'] ?? null,
            $linkPreview['url'] ?? null,
            $body,
        ] as $candidate) {
            $url = $this->metadata->extractFirstUrl(is_string($candidate) ? $candidate : null);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
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

        Pet::query()
            ->whereIn('id', $petIds->all())
            ->increment('posts_count');
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
     * @return list<array{temporary_path: string, media_type: string, alt_text: ?string, order: int}>
     */
    private function normalizeTemporaryMedia(mixed $media): array
    {
        if (! is_array($media)) {
            return [];
        }

        return collect($media)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item, int $index): array => [
                'temporary_path' => (string) ($item['temporary_path'] ?? ''),
                'media_type' => ($item['media_type'] ?? 'image') === 'video' ? 'video' : 'image',
                'alt_text' => $this->normalizeNullableString($item['alt_text'] ?? null),
                'order' => (int) ($item['order'] ?? $index),
            ])
            ->filter(fn (array $item): bool => $item['temporary_path'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, UploadedFile>  $mediaFiles
     * @param  list<array{temporary_path: string, media_type: string, alt_text: ?string, order: int}>  $temporaryMedia
     */
    private function resolveType(array $mediaFiles, array $temporaryMedia): string
    {
        if ($mediaFiles === [] && $temporaryMedia === []) {
            return Post::TYPE_TEXT;
        }

        foreach ($mediaFiles as $mediaFile) {
            if (str_starts_with((string) $mediaFile->getMimeType(), 'video/')) {
                return Post::TYPE_VIDEO;
            }
        }

        foreach ($temporaryMedia as $media) {
            if ($media['media_type'] === 'video') {
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

    private function resolveCreationStatus(mixed $status, ?CarbonInterface $scheduledPublishAt): PostStatus
    {
        $normalized = $this->normalizeStatus($status);

        if ($normalized === PostStatus::Draft) {
            return PostStatus::Draft;
        }

        return $scheduledPublishAt instanceof CarbonInterface
            ? PostStatus::Scheduled
            : PostStatus::Published;
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

    private function resolveScheduledPublishAt(mixed $publishedAt): ?CarbonInterface
    {
        if ($publishedAt instanceof CarbonInterface) {
            return $publishedAt;
        }

        if (is_string($publishedAt) && $publishedAt !== '') {
            return CarbonImmutable::parse($publishedAt);
        }

        return null;
    }
}
