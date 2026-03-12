<?php

namespace App\Http\Controllers;

use App\Actions\Engagement\SetReactionAction;
use App\Http\Requests\ReactToPostRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class ReactionController extends Controller
{
    public function __construct(private readonly SetReactionAction $setReaction) {}

    public function react(ReactToPostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('react', $post);

        $result = $this->setReaction->handle($request->user(), $post, $request->validated('type'));

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
