<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\ReactToPostRequest;
use App\Models\Content\Post;
use App\Models\Content\PostReaction;
use Illuminate\Http\JsonResponse;

class PostReactionController extends Controller
{
    public function react(ReactToPostRequest $request, Post $post): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($post->canBeViewedBy($viewer), 403);

        $type = $request->validated('type');

        $reaction = PostReaction::query()
            ->where('post_id', $post->id)
            ->where('user_id', $viewer->id)
            ->first();

        $message = 'Reaction added.';
        $currentType = $type;

        if ($reaction && $reaction->type === $type) {
            $reaction->delete();
            $message = 'Reaction removed.';
            $currentType = null;
        } elseif ($reaction) {
            $reaction->update(['type' => $type]);
            $message = 'Reaction updated.';
        } else {
            PostReaction::query()->create([
                'post_id' => $post->id,
                'user_id' => $viewer->id,
                'type' => $type,
            ]);
        }

        $post->refreshLikesCount();
        $post->refresh();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'post_id' => $post->id,
                'likes_count' => $post->likes_count,
                'current_reaction' => $currentType,
            ],
        ]);
    }
}
