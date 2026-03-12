<?php

namespace App\Actions\Posts;

use App\Actions\Hashtags\SyncPostHashtagsAction;
use App\Models\Post;

class ProcessTagsAction
{
    public function __construct(private readonly SyncPostHashtagsAction $hashtags) {}

    public function handle(Post $post): void
    {
        $this->hashtags->handle($post);
    }
}
