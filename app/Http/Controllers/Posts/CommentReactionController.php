<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\ReactToCommentRequest;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Services\CommentQualityService;
use Illuminate\Http\JsonResponse;

class CommentReactionController extends Controller
{
    public function react(ReactToCommentRequest $request, Post $post, Comment $comment, CommentQualityService $quality): JsonResponse
    {
        abort_unless((int) $comment->post_id === (int) $post->getKey(), 404);
        abort_unless($post->canBeViewedBy($request->user()), 403);
        abort_if($comment->trashed(), 404);
        $this->authorize('react', $comment);

        $type = Reaction::normalizeType($request->validated('type'));
        $reaction = $comment->toggleReaction($request->user(), $type);
        $comment->refresh();
        $quality->refresh($comment);

        return response()->json([
            'success' => true,
            'data' => [
                'comment_id' => $comment->id,
                'current_reaction' => $reaction?->type,
                'reactions_count' => (int) $comment->reactions_count,
                'reaction_counts' => [
                    'paw' => (int) $comment->paw_count,
                    'love' => (int) $comment->love_count,
                ],
            ],
        ]);
    }

    public function reactToComment(ReactToCommentRequest $request, Comment $comment, CommentQualityService $quality): JsonResponse
    {
        $post = $comment->post;

        abort_unless($post !== null && $post->canBeViewedBy($request->user()), 403);
        abort_if($comment->trashed(), 404);
        $this->authorize('react', $comment);

        $type = Reaction::normalizeType($request->validated('type'));
        $reaction = $comment->toggleReaction($request->user(), $type);
        $comment->refresh();
        $quality->refresh($comment);

        return response()->json([
            'success' => true,
            'data' => [
                'comment_id' => $comment->id,
                'current_reaction' => $reaction?->type,
                'reactions_count' => (int) $comment->reactions_count,
                'reaction_counts' => [
                    'paw' => (int) $comment->paw_count,
                    'love' => (int) $comment->love_count,
                ],
            ],
        ]);
    }
}
