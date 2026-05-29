<?php

namespace App\Support\Posts;

use App\Models\Content\Post;
use RuntimeException;

class PostCreationResult
{
    private function __construct(
        public readonly bool $duplicateDetected,
        public readonly ?Post $post,
        public readonly ?string $contentHash,
        public readonly ?int $duplicatePostId = null,
    ) {}

    public static function created(Post $post, ?string $contentHash): self
    {
        return new self(false, $post, $contentHash);
    }

    public static function duplicate(Post $post, string $contentHash): self
    {
        return new self(true, null, $contentHash, (int) $post->getKey());
    }

    public function createdPost(): Post
    {
        if (! $this->post instanceof Post) {
            throw new RuntimeException('The post creation result does not contain a created post.');
        }

        return $this->post;
    }
}
