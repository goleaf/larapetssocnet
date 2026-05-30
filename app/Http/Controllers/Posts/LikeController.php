<?php

namespace App\Http\Controllers\Posts;

use App\Actions\Engagement\ToggleReactionAction;
use App\Http\Controllers\Controller;
use App\Models\Content\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function __construct(private readonly ToggleReactionAction $toggleReaction) {}

    public function toggle(Request $request, Post $post): JsonResponse
    {
        $this->authorize('react', $post);

        $result = $this->toggleReaction->handle($request->user(), $post);

        return response()->json([
            'liked' => $result['action'] !== 'removed',
            'count' => $result['likes_count'],
            'likes_count' => $result['likes_count'],
            'reactions_count' => $result['reactions_count'],
            'reaction_counts' => $result['reaction_counts'],
            'current_reaction' => $result['current_reaction'],
        ]);
    }
}
