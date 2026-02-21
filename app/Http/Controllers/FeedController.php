<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\UserFollow;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();

        $followedUserIds = UserFollow::query()
            ->select('followed_id')
            ->where('follower_id', $viewer->id);

        $posts = Post::query()
            ->with(['user', 'hashtags'])
            ->where(function ($query) use ($viewer, $followedUserIds): void {
                $query
                    ->where('user_id', $viewer->id)
                    ->orWhere(function ($followedPostsQuery) use ($followedUserIds): void {
                        $followedPostsQuery
                            ->whereIn('user_id', $followedUserIds)
                            ->whereIn('visibility', [Post::VISIBILITY_PUBLIC, Post::VISIBILITY_FOLLOWERS]);
                    });
            })
            ->notBlockedFor($viewer)
            ->latest()
            ->paginate(15);

        return view('feed.index', [
            'posts' => $posts,
        ]);
    }
}
