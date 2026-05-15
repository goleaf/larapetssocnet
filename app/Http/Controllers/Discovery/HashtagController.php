<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Models\Content\Hashtag;
use App\Models\Content\Post;
use App\Services\HashtagService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HashtagController extends Controller
{
    public function __construct(private readonly HashtagService $hashtags) {}

    public function show(Request $request, Hashtag $hashtag): View
    {
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['latest', 'trending', 'top'], true) ? $sort : 'latest';
        $viewer = $request->user();

        $posts = Post::query()
            ->byTag($hashtag->slug)
            ->published()
            ->visibleTo($viewer)
            ->withFeedRelations($viewer)
            ->when(
                $sort === 'trending',
                fn ($query) => $query->trending(),
                fn ($query) => $query->when(
                    $sort === 'top',
                    fn ($topQuery) => $topQuery->topRated(),
                    fn ($latestQuery) => $latestQuery->latest('posts.created_at')
                )
            )
            ->paginate(20)
            ->withQueryString();

        $relatedHashtags = $this->hashtags->relatedHashtags($hashtag, $viewer, 6);

        return view('discovery.hashtags.show', [
            'hashtag' => $hashtag,
            'posts' => $posts,
            'sort' => $sort,
            'relatedHashtags' => $relatedHashtags,
        ]);
    }
}
