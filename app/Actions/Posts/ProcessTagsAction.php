<?php

namespace App\Actions\Posts;

use App\Models\Post;
use App\Services\HashtagService;

class ProcessTagsAction
{
    public function __construct(private readonly HashtagService $hashtags) {}

    public function handle(Post $post): void
    {
        $this->hashtags->syncHashtags($post);
    }
}
