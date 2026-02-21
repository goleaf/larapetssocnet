<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\ReactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    public function __construct(private readonly ReactionService $reactionService) {}

    public function react(Request $request, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:love,cute,funny,wow,sad,support'],
        ]);

        $result = $this->reactionService->react($request->user(), $post, $validated['type']);

        return response()->json([
            'success' => true,
            'action' => $result['action'],
            'data' => [
                'post_id' => $post->id,
                'likes_count' => $result['likes_count'],
                'current_reaction' => $result['current_reaction'],
            ],
        ]);
    }
}
