<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReactToCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class CommentReactionController extends Controller
{
    public function react(ReactToCommentRequest $request, Post $post, Comment $comment): JsonResponse
    {
        abort_unless($comment->post_id === $post->id, 404);
        abort_unless($post->canBeViewedBy($request->user()), 403);

        $type = $request->validated('type');
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

        $type = $request->validated('type');
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
