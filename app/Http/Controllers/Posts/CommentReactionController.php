<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\ReactToCommentRequest;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use Illuminate\Http\JsonResponse;

class CommentReactionController extends Controller
{
    public function react(ReactToCommentRequest $request, Post $post, Comment $comment): JsonResponse
    {
        abort_unless((int) $comment->post_id === (int) $post->getKey(), 404);
        abort_unless($post->canBeViewedBy($request->user()), 403);
        abort_if($comment->trashed(), 404);
        $this->authorize('react', $comment);
        $this->authorize('react', $comment);

        $type = Reaction::normalizeType($request->validated('type'));
        $reaction = $comment->toggleReaction($request->user(), $type);
        $comment->refresh();

        return response()->json([
            'success' => true,
            'data' => [
                'comment_id' => $comment->id,
                'current_reaction' => $reaction?->type,
                'reactions_count' => (int) $comment->reactions_count,
            ],
        ]);
    }

    public function reactToComment(ReactToCommentRequest $request, Comment $comment): JsonResponse
    {
        $post = $comment->post;

        abort_unless($post !== null && $post->canBeViewedBy($request->user()), 403);
        abort_if($comment->trashed(), 404);
        $this->authorize('react', $comment);
        $this->authorize('react', $comment);

        $type = Reaction::normalizeType($request->validated('type'));
        $reaction = $comment->toggleReaction($request->user(), $type);
        $comment->refresh();

        return response()->json([
            'success' => true,
            'data' => [
                'comment_id' => $comment->id,
                'current_reaction' => $reaction?->type,
                'reactions_count' => (int) $comment->reactions_count,
            ],
        ]);
    }
}
