<?php

declare(strict_types=1);

namespace App\Actions\Engagement;

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Services\ReactionService;

class ToggleReactionAction
{
    public function __construct(private readonly ReactionService $reactions) {}

    /**
     * @return array{action: 'added'|'changed'|'removed', current_reaction: ?string, likes_count: int}
     */
    public function handle(User $actor, Post $post): array
    {
        return $this->reactions->react($actor, $post, Reaction::TYPE_LOVE);
    }
}
