<?php

namespace App\Actions\Posts;

use App\Models\Post;
use App\Services\ContentService;
use Illuminate\Support\Facades\DB;

class UpdatePostAction
{
    public function __construct(
        private readonly ContentService $content,
        private readonly ProcessTagsAction $processTags,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Post $post, array $data): Post
    {
        return DB::transaction(function () use ($post, $data): Post {
            $nextBody = array_key_exists('body', $data)
                ? $this->normalizeNullableString($data['body'])
                : $post->body;

            $post->update([
                'body' => $nextBody,
                'body_html' => $nextBody ? $this->content->process($nextBody) : null,
                'visibility' => $data['visibility'] ?? $post->visibility,
                'location' => $this->normalizeNullableString($data['location'] ?? $post->location),
                'pet_id' => $data['pet_id'] ?? ($data['tagged_pets'][0] ?? $post->pet_id),
                'tagged_pets' => $data['tagged_pets'] ?? $post->tagged_pets,
            ]);

            $this->processTags->handle($post);

            return $post->fresh() ?? $post;
        });
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
