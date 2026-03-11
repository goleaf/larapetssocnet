<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PostService
{
    public function __construct(
        private ContentService $content
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

            $post = Post::create([
                'user_id' => $author->id,
                'pet_id' => $data['pet_id'] ?? ($data['tagged_pets'][0] ?? null),
                'body' => $body,
                'body_html' => $bodyHtml,
                'type' => $type,
                'visibility' => $data['visibility'] ?? 'public',
                'location' => $data['location'] ?? null,
                'tagged_pets' => $data['tagged_pets'] ?? null,
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

            $post->update([
                'body' => $body,
                'body_html' => $bodyHtml,
                'visibility' => $data['visibility'] ?? $post->visibility,
                'location' => $data['location'] ?? $post->location,
                'pet_id' => $data['pet_id'] ?? ($data['tagged_pets'][0] ?? $post->pet_id),
                'tagged_pets' => $data['tagged_pets'] ?? $post->tagged_pets,
            ]);

            return $post->fresh();
        });
    }

    public function delete(Post $post): void
    {
        DB::transaction(function () use ($post) {
            $post->delete();
        });
    }

    public function pin(Post $post): void
    {
        DB::transaction(function () use ($post) {
            $post->author->posts()
                ->where('is_pinned', true)
                ->update(['is_pinned' => false]);

            $post->updateQuietly(['is_pinned' => true]);
        });
    }

    public function unpin(Post $post): void
    {
        $post->updateQuietly(['is_pinned' => false]);
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
}
