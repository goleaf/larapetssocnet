<?php

namespace App\Http\Controllers;

use App\Actions\Engagement\ToggleSavedPostAction;
use App\Models\Post;
use App\Services\SavedPostsQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedPostController extends Controller
{
    public function __construct(
        private readonly ToggleSavedPostAction $toggleSaved,
        private readonly SavedPostsQueryService $savedPosts,
    ) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();

        $savedPosts = $this->savedPosts->paginateForViewer($viewer);

        return view('saved.index', [
            'savedPosts' => $savedPosts,
        ]);
    }

    public function toggle(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        $this->authorize('save', $post);

        $saved = $this->toggleSaved->handle($request->user(), $post);

        if (! $request->expectsJson()) {
            return back()->with('status', $saved ? 'Post saved.' : 'Post unsaved.');
        }

        return response()->json([
            'success' => true,
            'saved' => $saved,
        ]);
    }
}
