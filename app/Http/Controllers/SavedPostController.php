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

        $savedPosts = SavedPost::query()
            ->where('user_id', $viewer->id)
            ->with([
                'post' => fn ($query) => $query
                    ->with(['user', 'hashtags'])
                    ->published()
                    ->visibleTo($viewer),
            ])
            ->latest()
            ->paginate(15);

        return view('saved.index', [
            'savedPosts' => $savedPosts,
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
