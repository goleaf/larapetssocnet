<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    public function __construct(
        private PostService $posts
    ) {}

    public function create()
    {
        return view('posts.create');
    }

    public function show(Post $post)
    {
        $this->authorize('view', $post);

        return view('posts.show', compact('post'));
    }

    public function edit(Post $post)
    {
        Gate::authorize('update', $post);

        return view('posts.edit', compact('post'));
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $this->posts->create(
            author: $request->user(),
            data: $request->safe()->except(['media', 'photos', 'video']),
            mediaFiles: $request->mediaFiles(),
        );

        return back()->with('success', 'Post created successfully.');
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $this->posts->update($post, $request->validated());

        return redirect()->route('posts.show', $post)->with('success', 'Post updated successfully.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        abort_unless((int) $post->user_id === (int) $request->user()->id, 403);

        $this->posts->delete($post);

        return back()->with('success', 'Post deleted successfully.');
    }

    public function pin(Post $post): RedirectResponse
    {
        Gate::authorize('pin', $post);
        $this->posts->pin($post);

        return back()->with('success', 'Post pinned successfully.');
    }

    public function unpin(Post $post): RedirectResponse
    {
        Gate::authorize('pin', $post);
        $this->posts->unpin($post);

        return back()->with('success', 'Post unpinned successfully.');
    }
}
