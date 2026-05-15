<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Models\Content\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PinnedPostController extends Controller
{
    public function pin(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        Post::query()
            ->where('user_id', $request->user()->id)
            ->where('is_pinned', true)
            ->update(['is_pinned' => false]);

        $post->update(['is_pinned' => true]);

        return back()->with('status', 'Post pinned on profile.');
    }

    public function unpin(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        $post->update(['is_pinned' => false]);

        return back()->with('status', 'Post unpinned.');
    }
}
