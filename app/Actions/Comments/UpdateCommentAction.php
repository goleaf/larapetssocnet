<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Models\Content\Comment;
use App\Models\Identity\User;
use App\Services\CommentService;
use Illuminate\Support\Facades\Gate;

class UpdateCommentAction
{
    public function __construct(private readonly CommentService $comments) {}

    public function handle(User $actor, Comment $comment, string $body): Comment
    {
        Gate::forUser($actor)->authorize('update', $comment);

        return $this->comments->update($comment, $body);
    }
}
