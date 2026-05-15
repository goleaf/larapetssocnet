<?php

declare(strict_types=1);

namespace App\Actions\Engagement;

use App\Models\Content\Post;
use App\Models\Identity\User;
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
