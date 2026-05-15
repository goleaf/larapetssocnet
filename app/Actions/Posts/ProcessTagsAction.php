<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Actions\Hashtags\SyncPostHashtagsAction;
use App\Models\Content\Post;

class ProcessTagsAction
{
    public function __construct(private readonly SyncPostHashtagsAction $hashtags) {}

    public function handle(Post $post): void
    {
        $this->hashtags->handle($post);
    }
}
