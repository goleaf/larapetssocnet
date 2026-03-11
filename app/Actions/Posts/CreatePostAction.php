<?php

namespace App\Actions\Posts;

use App\Enums\PostStatus;
use App\Events\PostCreated;
use App\Models\Post;
use App\Models\User;
use App\Services\ContentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreatePostAction
{
    public function __construct(
        private readonly ContentService $content,
        private readonly ProcessTagsAction $processTags,
        private readonly UploadMediaAction $uploadMedia,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): Post
    {
        return DB::transaction(function () use ($user, $data): Post {
            $mediaFiles = $this->normalizeMediaFiles($data['media_files'] ?? []);
            $body = $this->normalizeNullableString($data['body'] ?? null);

            $post = Post::query()->create([
                'user_id' => $user->getKey(),
                'pet_id' => $data['pet_id'] ?? ($data['tagged_pets'][0] ?? null),
                'body' => $body,
                'body_html' => $body ? $this->content->process($body) : null,
                'type' => $this->resolveType($mediaFiles),
                'status' => ($data['status'] ?? PostStatus::Published) instanceof PostStatus
                    ? ($data['status'] ?? PostStatus::Published)->value
                    : (string) ($data['status'] ?? PostStatus::Published->value),
                'published_at' => $data['published_at'] ?? now(),
                'visibility' => $data['visibility'] ?? Post::VISIBILITY_PUBLIC,
                'location' => $this->normalizeNullableString($data['location'] ?? null),
                'tagged_pets' => $data['tagged_pets'] ?? null,
            ]);

            $this->processTags->handle($post);

            if ($mediaFiles !== []) {
                $this->uploadMedia->handle($post, $mediaFiles);
            }

            PostCreated::dispatch($post);

            return $post;
        });
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
}
