<?php

namespace App\Http\Controllers;

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

        $user = $request->user()->loadCount([
            'posts',
            'acceptedFollowers as followers_count',
            'acceptedFollowing as following_count',
        ]);

        $feedData = $this->feed->getFeed(
            user: $request->user(),
            type: $type,
            perPage: 15,
        );

        $sidebarData = $this->feed->getSidebarData($request->user());

        return view('feed.index', array_merge(
            $feedData,
            $sidebarData,
            compact('user', 'type'),
        ));
    }
}
