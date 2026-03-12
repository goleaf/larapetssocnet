<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\User;
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
