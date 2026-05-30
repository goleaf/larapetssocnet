<?php

namespace App\Actions\Comments;

use App\Models\Content\Post;

class CreateCommentData
{
    /**
     * @param  array{gif_url?: string|null, gif_preview_url?: string|null, gif_title?: string|null, gif_provider?: string|null}|null  $gif
     */
    public function __construct(
        public readonly Post $post,
        public readonly string $body,
        public readonly ?int $parentId = null,
        public readonly ?array $gif = null,
    ) {}
}
