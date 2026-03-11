<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\FeedService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function index(Request $request): View
    {
        $type = in_array($request->string('type')->toString(), ['text', 'photo', 'video'], true)
            ? $request->string('type')->toString()
            : null;

        $user = $request->user()->loadFeedContext();
        $posts = Post::paginateMainFeedResults($user, $type);

        $postIds = $posts->getCollection()->modelKeys();
        $myReactions = Post::reactionMapForViewer($user, $postIds);
        $mySaved = Post::savedMapForViewer($user, $postIds);

        $sidebarData = $this->feed->getSidebarData($request->user());

        return view('feed.index', array_merge(
            compact('posts', 'myReactions', 'mySaved'),
            $sidebarData,
            compact('user', 'type'),
        ));
    }
}
