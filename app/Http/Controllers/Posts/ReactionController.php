<?php

namespace App\Http\Controllers\Posts;

use App\Actions\Engagement\SetReactionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\ReactToPostRequest;
use App\Models\Content\Post;
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
                'reactions_count' => $result['reactions_count'],
                'reaction_counts' => $result['reaction_counts'],
                'current_reaction' => $result['current_reaction'],
            ],
        ]);
    }
}
