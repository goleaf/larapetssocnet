<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Services\CommentGifService;
use App\Support\Search\SearchInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentGifController extends Controller
{
    public function __invoke(Request $request, CommentGifService $gifs): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        $query = SearchInput::normalize($validated['q']);

        return response()->json([
            'results' => SearchInput::hasSearchableLength($query) ? $gifs->search($query) : [],
        ]);
    }
}
