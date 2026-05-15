<?php

namespace App\Actions\Hashtags;

use App\Models\Content\Post;
use App\Services\HashtagService;

class ExtractHashtagsFromPostAction
{
    public function __construct(private readonly HashtagService $hashtags) {}

    /**
     * @return list<string>
     */
    public function handle(Post $post): array
    {
        return $this->hashtags->extract($post->body ?? '');
    }
}
