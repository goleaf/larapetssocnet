<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PostService
{
    public function __construct(
        private readonly ContentService $content,
        private readonly HashtagService $hashtags,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function create(User $author, array $data, ?UploadedFile $video, array $photos): Post
    {
        return DB::transaction(function () use ($author, $data, $video, $photos): Post {
            $body = isset($data['body']) ? trim((string) $data['body']) : null;
            $bodyHtml = $body ? $this->content->process($body) : null;
            $type = $this->resolveType($photos, $video);
            $taggedPets = collect($data['tagged_pets'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
            $primaryPetId = $data['pet_id'] ?? ($taggedPets[0] ?? null);

            $post = Post::query()->create([
                'user_id' => $author->id,
                'pet_id' => $primaryPetId,
                'body' => $body,
                'body_html' => $bodyHtml,
                'type' => $type,
                'visibility' => $data['visibility'] ?? Post::VISIBILITY_PUBLIC,
                'location' => $data['location'] ?? null,
                'status' => 'published',
                'tagged_pets' => $taggedPets,
            ]);

            foreach ($photos as $photo) {
                $post->addMedia($photo)->toMediaCollection('photos');
            }

            if ($video) {
                $post->addMedia($video)->toMediaCollection('videos');
            }

            return $post->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Post $post, array $data): Post
    {
        return DB::transaction(function () use ($post, $data): Post {
            $body = array_key_exists('body', $data) ? trim((string) ($data['body'] ?? '')) : $post->body;
            $bodyHtml = filled((string) $body) ? $this->content->process((string) $body) : null;
            $taggedPets = array_key_exists('tagged_pets', $data)
                ? collect($data['tagged_pets'] ?? [])
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->unique()
                    ->values()
                    ->all()
                : $post->tagged_pets;
            $primaryPetId = $data['pet_id'] ?? (($taggedPets[0] ?? null) ?: $post->pet_id);

            $post->update([
                'body' => $body,
                'body_html' => $bodyHtml,
                'visibility' => $data['visibility'] ?? $post->visibility,
                'location' => $data['location'] ?? $post->location,
                'pet_id' => $primaryPetId,
                'tagged_pets' => $taggedPets,
            ]);

            return $post->fresh();
        });
    }

    public function delete(Post $post): void
    {
        DB::transaction(fn () => $post->delete());
    }

    public function pin(Post $post): void
    {
        DB::transaction(function () use ($post): void {
            $post->author->posts()->where('is_pinned', true)->get()->each(
                fn (Post $p) => $p->updateQuietly(['is_pinned' => false])
            );

            $post->updateQuietly(['is_pinned' => true]);
        });
    }

    public function unpin(Post $post): void
    {
        $post->updateQuietly(['is_pinned' => false]);
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     */
    private function resolveType(array $photos, ?UploadedFile $video): string
    {
        if ($video) {
            return Post::TYPE_VIDEO;
        }

        if ($photos !== []) {
            return Post::TYPE_PHOTO;
        }

        return Post::TYPE_TEXT;
    }
}
