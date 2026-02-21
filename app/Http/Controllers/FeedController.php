<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();

        $posts = Post::query()
            ->with(['user', 'hashtags'])
            ->forFeed($viewer)
            ->latest()
            ->paginate(15);

        return view('feed.index', [
            'posts' => $posts,
        ]);
    }
}
