<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $posts = Post::withTrashed()
            ->with(['author', 'media'])
            ->when($request->q, fn ($q, $s) => $q->where('body', 'like', "%{$s}%"))
            ->when($request->filter === 'deleted', fn ($q) => $q->onlyTrashed())
            ->when($request->filter === 'reported', fn ($q) => $q->whereHas('reports', fn ($r) => $r->where('status', 'pending')))
            ->latest()
            ->paginate(30);

        return view('admin.posts.index', compact('posts'));
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
