<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\FeedService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function __construct(private FeedService $feed)
    {
    }

    public function index(Request $request): View
    {
        $type = in_array($request->string('type')->toString(), ['text', 'photo', 'video'], true)
            ? $request->string('type')->toString()
            : null;


        $user = $request->user()
            ->load([
                'acceptedFollowing:id',
                'sentPendingRequests:id',
            ])
            ->loadCount([
                'posts',
                'acceptedFollowers as followers_count',
                'acceptedFollowing as following_count',
            ]);

        $hasFollowing = $user->acceptedFollowing()->exists();

        $posts = Post::query()
            ->when(
                $hasFollowing,
                fn($query) => $query->forFeed($user),
                fn($query) => $query->visibleTo($user)
            )
            ->whereDoesntHave('author', fn($query) => $query->where('is_banned', true))
            ->whereNotIn('user_id', $user->blocking()->select('users.id'))
            ->whereNotIn('user_id', $user->blockedBy()->select('users.id'))
            ->with([
                'user',
                'author',
                'author.media',
                'pet',
                'pet.media',
                'media',
                'postMedia',
                'likes',
                'comments.user',
            ])
            ->withCount([
                'comments',
                'likes',
            ])
            ->when($type !== null, fn($query) => $query->byType($type))
            ->orderByDesc('posts.created_at')
            ->paginate(15)
            ->withQueryString();

        $postIds = $posts->getCollection()->modelKeys();

        $myReactions = $user->reactions()
            ->whereIn('reactable_id', $postIds)
            ->where('reactable_type', Post::class)
            ->get()
            ->keyBy('reactable_id');

        $mySaved = $user->savedPosts()
            ->whereIn('posts.id', $postIds)
            ->pluck('posts.id')
            ->flip();

        $sidebarData = $this->feed->getSidebarData($request->user());

        return view('feed.index', array_merge(
            compact('posts', 'myReactions', 'mySaved'),
            $sidebarData,
            compact('user', 'type'),
        ));
    }
}
