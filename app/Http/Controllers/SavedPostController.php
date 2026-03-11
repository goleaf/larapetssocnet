<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SavedPost;
use App\Services\SavedPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedPostController extends Controller
{
    public function __construct(private readonly SavedPostService $savedPostService) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();

        $savedPosts = SavedPost::paginateForViewer($viewer);

        return view('saved.index', [
            'savedPosts' => $savedPosts,
        ]);
    }

    public function toggle(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $post);

        $saved = $this->savedPostService->toggle($request->user(), $post);

        if (! $request->expectsJson()) {
            return back()->with('status', $saved ? 'Post saved.' : 'Post unsaved.');
        }

        return response()->json([
            'success' => true,
            'saved' => $saved,
        ]);
    }
}
