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
            ->with(['user', 'hashtags'])
            ->whereHas('hashtags', fn ($query) => $query->where('hashtags.id', $hashtag->id))
            ->visibleTo($request->user())
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('hashtags.show', [
            'hashtag' => $hashtag,
            'posts' => $posts,
        ]);
    }
}
