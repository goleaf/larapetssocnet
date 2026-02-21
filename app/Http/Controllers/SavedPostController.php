<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SavedPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedPostController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();

        $posts = Post::query()
            ->select('posts.*')
            ->join('saved_posts', 'saved_posts.post_id', '=', 'posts.id')
            ->where('saved_posts.user_id', $viewer->id)
            ->with(['user', 'hashtags'])
            ->published()
            ->visibleTo($viewer)
            ->orderByDesc('saved_posts.created_at')
            ->paginate(15);

        return view('saved.index', [
            'posts' => $posts,
        ]);
    }

    public function toggle(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->canBeViewedBy($request->user()), 403);

        $savedPost = SavedPost::query()
            ->where('post_id', $post->id)
            ->where('user_id', $request->user()->id)
            ->first();

        $message = 'Post saved.';

        if ($savedPost) {
            $savedPost->delete();
            $message = 'Post removed from saved.';
        } else {
            SavedPost::query()->create([
                'post_id' => $post->id,
                'user_id' => $request->user()->id,
            ]);
        }

        return back()->with('status', $message);
    }
}
