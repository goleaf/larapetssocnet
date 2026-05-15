<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Models\Content\Comment;
use App\Models\Identity\User;
use App\Services\CommentService;
use Illuminate\Support\Facades\Gate;

class DeleteCommentAction
{
    public function __construct(private readonly CommentService $comments) {}

    public function handle(User $actor, Comment $comment): void
    {
        Gate::forUser($actor)->authorize('delete', $comment);

        $this->comments->delete($comment);
    }
}
