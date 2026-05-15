<?php

namespace App\Actions\Comments;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\CommentService;
use Illuminate\Support\Facades\Gate;

class CreateCommentAction
{
    public function __construct(private readonly CommentService $comments) {}

    public function handle(User $actor, Post $post, string $body, ?int $parentId = null): Comment
    {
        Gate::forUser($actor)->authorize('create', [Comment::class, $post]);

        $parent = null;

        if ($parentId !== null) {
            $parent = Comment::query()->whereKey($parentId)->firstOrFail();
            Gate::forUser($actor)->authorize('reply', $parent);
        }

        return $this->comments->create($post, $actor, $body, $parent);
    }
}
