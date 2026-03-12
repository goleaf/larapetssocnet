<?php

namespace App\Actions\Engagement;

use App\Models\Post;
use App\Models\User;
use App\Services\ShareService;

class TrackShareAction
{
    public function __construct(private readonly ShareService $shares) {}

    /**
     * @return array{shared: bool, shares_count: int, url: string}
     */
    public function handle(User $actor, Post $post, string $method): array
    {
        return $this->shares->track($actor, $post, $method);
    }
}
