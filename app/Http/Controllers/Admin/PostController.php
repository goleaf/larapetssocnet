<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content\Post;
use App\Services\ModerationService;
use App\Support\Search\SearchInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $search = SearchInput::normalize($request->input('q'));

        $posts = Post::withTrashed()
            ->with(['author', 'media'])
            ->when(SearchInput::hasSearchableLength($search), fn ($q) => $q->where('body', 'like', SearchInput::containsPattern($search)))
            ->when($request->filter === 'deleted', fn ($q) => $q->onlyTrashed())
            ->when($request->filter === 'reported', fn ($q) => $q->whereHas('reports', fn ($r) => $r->where('status', 'pending')))
            ->latest()
            ->paginate(30);

        return view('admin.posts.index', ['posts' => $posts, 'q' => $search]);
    }

    public function destroy(Post $post): JsonResponse
    {
        app(ModerationService::class)->deletePost($post, auth()->user());

        return response()->json(['success' => true]);
    }

    public function restore(int $id): JsonResponse
    {
        Post::withTrashed()->findOrFail($id)->restore();

        return response()->json(['success' => true]);
    }
}
