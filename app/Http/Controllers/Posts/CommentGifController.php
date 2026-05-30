<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Services\CommentGifService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentGifController extends Controller
{
    public function __invoke(Request $request, CommentGifService $gifs): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:80'],
        ]);

        return response()->json([
            'results' => $gifs->search((string) $validated['q']),
        ]);
    }
}
