<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PostService
{
    public function __construct(
        private ContentService $content,
        private HashtagService $hashtags
    ) {}

    public function create(User $author, array $data, ?UploadedFile $video, array $photos): Post
    {
        return DB::transaction(function () use ($author, $data, $video, $photos) {
            $body = $data['body'] ?? null;
            $bodyHtml = $body ? $this->content->process($body) : null;
            $type = $this->resolveType($photos, $video);

            $post = Post::create([
                'user_id' => $author->id,
                'pet_id' => $data['pet_id'] ?? null,
                'body' => $body,
                'body_html' => $bodyHtml,
                'type' => $type,
                'visibility' => $data['visibility'] ?? 'public',
                'location' => $data['location'] ?? null,
            ]);

            foreach ($photos as $photo) {
                $post->addMedia($photo)->toMediaCollection('photos');
            }

            if ($video) {
                $post->addMedia($video)->toMediaCollection('videos');
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
                'pet_id' => $data['pet_id'] ?? $post->pet_id,
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

    private function resolveType(array $photos, ?UploadedFile $video): string
    {
        if ($video) {
            return 'video';
        }
        if (! empty($photos)) {
            return 'photo';
        }

        return 'text';
    }
}
