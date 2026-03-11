<?php

namespace App\Http\Controllers;

use App\Models\Hashtag;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HashtagController extends Controller
{
    public function show(Request $request, Hashtag $hashtag): View
    {
        $posts = Post::query()
            ->byTag($hashtag->slug)
            ->published()
            ->visibleTo($request->user())
            ->with(['user', 'hashtags'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('hashtags.show', [
            'hashtag' => $hashtag,
            'posts' => $posts,
        ]);
    }
}
