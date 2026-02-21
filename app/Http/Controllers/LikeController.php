<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function toggle(Request $request, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $user = $request->user();

        $existingLike = Like::query()
            ->where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->first();

        $liked = true;

        if ($existingLike) {
            $existingLike->delete();
            $liked = false;
        } else {
            Like::query()->create([
                'user_id' => $user->id,
                'post_id' => $post->id,
                'created_at' => now(),
            ]);
        }

        $count = (int) $post->likes()->count();
        $post->update(['likes_count' => $count]);

        return response()->json([
            'liked' => $liked,
            'count' => $count,
            'likes_count' => $count,
        ]);
    }
}
