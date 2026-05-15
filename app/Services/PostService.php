<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PostService
{
    public function __construct(
        private ContentService $content,
        private PostMetadataService $metadata
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>|UploadedFile|null  $mediaFiles
     * @param  array<int, mixed>  $legacyMediaMeta
     */
    public function create(
        User $author,
        array $data,
        array|UploadedFile|null $mediaFiles = null,
        array $legacyMediaMeta = []
    ): Post {
        return DB::transaction(function () use ($author, $data, $mediaFiles): Post {
            $mediaFiles = $this->normalizeMediaFiles($mediaFiles);

            $body = $data['body'] ?? null;
            $bodyHtml = $body ? $this->content->process($body) : null;
            $type = $this->resolveType($mediaFiles);
            $status = $this->normalizeStatus($data['status'] ?? null);
            $publishedAt = $this->resolvePublishedAt($status, $data['published_at'] ?? null);
            $metadata = $this->metadata->normalize(is_array($data['metadata'] ?? null) ? $data['metadata'] : null);

            $post = Post::create([
                'user_id' => $author->id,
                'pet_id' => $data['pet_id'] ?? ($data['tagged_pets'][0] ?? null),
                'body' => $body,
                'body_html' => $bodyHtml,
                'type' => $type,
                'status' => $status->value,
                'published_at' => $publishedAt,
                'visibility' => $data['visibility'] ?? 'public',
                'location' => $data['location'] ?? null,
                'tagged_pets' => $data['tagged_pets'] ?? null,
                'metadata' => $metadata,
            ]);

            foreach ($mediaFiles as $index => $mediaFile) {
                $isVideo = str_starts_with((string) $mediaFile->getMimeType(), 'video/');
                $storedMedia = $post->addMedia($mediaFile)
                    ->toMediaCollection($this->resolveMediaCollection($mediaFile), 'public');

                $post->postMedia()->create([
                    'file_path' => $storedMedia->getPathRelativeToRoot(),
                    'media_type' => $isVideo ? 'video' : 'image',
                    'order' => $index,
                ]);
            }

            return $post;
        });
    }

    public function update(Post $post, array $data): Post
    {
        return DB::transaction(function () use ($post, $data) {
            $body = $data['body'] ?? $post->body;
            $bodyHtml = $body ? $this->content->process($body) : null;
            $metadata = array_key_exists('metadata', $data)
                ? $this->metadata->normalize(is_array($data['metadata']) ? $data['metadata'] : null)
                : $post->metadata;
            $status = $this->normalizeStatus($data['status'] ?? $post->status ?? PostStatus::Published);
            $publishedAt = $post->published_at;

            if (array_key_exists('status', $data) || array_key_exists('published_at', $data)) {
                $publishedAt = $this->resolvePublishedAt($status, $data['published_at'] ?? $publishedAt);
            }
            $editedAt = $post->edited_at;

            if (array_key_exists('body', $data) && $body !== $post->body) {
                $editedAt = now();
            }

            $post->update([
                'body' => $body,
                'body_html' => $bodyHtml,
                'visibility' => $data['visibility'] ?? $post->visibility,
                'location' => $data['location'] ?? $post->location,
                'pet_id' => $data['pet_id'] ?? ($data['tagged_pets'][0] ?? $post->pet_id),
                'tagged_pets' => $data['tagged_pets'] ?? $post->tagged_pets,
                'status' => $status->value,
                'published_at' => $publishedAt,
                'metadata' => $metadata,
                'edited_at' => $editedAt,
            ]);

            return $post->fresh();
        });
    }

    public function delete(Post $post): void
    {
        DB::transaction(function () use ($post): void {
            $post->delete();
        });
    }

    public function publish(Post $post, ?CarbonInterface $publishedAt = null): Post
    {
        return DB::transaction(function () use ($post, $publishedAt): Post {
            $post->update([
                'status' => PostStatus::Published->value,
                'published_at' => $publishedAt ?? now(),
            ]);

            return $post->refresh() ?? $post;
        });
    }

    public function schedule(Post $post, CarbonInterface $publishedAt): Post
    {
        return DB::transaction(function () use ($post, $publishedAt): Post {
            $post->update([
                'status' => PostStatus::Scheduled->value,
                'published_at' => $publishedAt,
            ]);

            return $post->refresh() ?? $post;
        });
    }

    public function unpublish(Post $post): Post
    {
        return DB::transaction(function () use ($post): Post {
            $post->update([
                'status' => PostStatus::Draft->value,
                'published_at' => null,
            ]);

            return $post->refresh() ?? $post;
        });
    }

    public function archive(Post $post): Post
    {
        return DB::transaction(function () use ($post): Post {
            $post->update([
                'status' => PostStatus::Archived->value,
            ]);

            return $post->refresh() ?? $post;
        });
    }

    public function pin(Post $post): void
    {
        DB::transaction(function () use ($post): void {
            $post->author->posts()
                ->where('is_pinned', true)
                ->updateQuietly(['is_pinned' => false, 'pinned_at' => null]);

            $post->updateQuietly(['is_pinned' => true, 'pinned_at' => now()]);
        });
    }

    public function unpin(Post $post): void
    {
        $post->updateQuietly(['is_pinned' => false, 'pinned_at' => null]);
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

    /**
     * @param  array<int, UploadedFile>|UploadedFile|null  $mediaFiles
     * @return array<int, UploadedFile>
     */
    private function normalizeMediaFiles(array|UploadedFile|null $mediaFiles): array
    {
        return collect(is_array($mediaFiles) ? $mediaFiles : [$mediaFiles])
            ->filter(fn ($file): bool => $file instanceof UploadedFile)
            ->values()
            ->all();
    }

    private function resolveMediaCollection(UploadedFile $mediaFile): string
    {
        return str_starts_with((string) $mediaFile->getMimeType(), 'video/')
            ? 'videos'
            : 'photos';
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
}
