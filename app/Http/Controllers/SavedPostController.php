<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SavedPost;
use App\Services\SavedPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedPostController extends Controller
{
    public function __construct(private readonly SavedPostService $savedPostService) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();

        $savedPosts = SavedPost::query()
            ->where('user_id', $viewer->id)
            ->with([
                'post' => fn ($query) => $query
                    ->with(['author', 'hashtags'])
                    ->visibleTo($viewer),
            ])
            ->latest()
            ->paginate(15);

        return view('saved.index', [
            'savedPosts' => $savedPosts,
        ]);
    }

    public function toggle(Request $request, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $saved = $this->savedPostService->toggle($request->user(), $post);

        return response()->json([
            'success' => true,
            'saved' => $saved,
        ]);
    }
}
