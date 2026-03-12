<?php

namespace App\Actions\Hashtags;

use App\Models\Post;
use App\Services\HashtagService;

class SyncPostHashtagsAction
{
    public function __construct(private readonly HashtagService $hashtags) {}

    public function handle(Post $post, bool $updateCounts = true): void
    {
        $this->hashtags->syncHashtags($post, $updateCounts);
    }
}
