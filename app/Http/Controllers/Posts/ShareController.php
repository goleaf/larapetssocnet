<?php

namespace App\Http\Controllers\Posts;

use App\Actions\Engagement\TrackShareAction;
use App\Actions\Posts\CreateRepostAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\ShareActionRequest;
use App\Models\Content\Post;
use Illuminate\Http\JsonResponse;

class ShareController extends Controller
{
    public function __construct(
        private readonly TrackShareAction $trackShare,
        private readonly CreateRepostAction $reposts,
    ) {}

    public function store(ShareActionRequest $request, Post $post): JsonResponse
    {
        $this->authorize('share', $post);

        if ($request->validated('method') === 'repost') {
            $result = $this->reposts->handle($request->user(), $post);

            return response()->json([
                'success' => true,
                'shared' => true,
                'shares_count' => $result['shares_count'],
                'url' => $result['original_url'],
                'repost_id' => $result['post']->getKey(),
                'repost_url' => $result['repost_url'],
            ]);
        }

        $result = $this->trackShare->handle(
            $request->user(),
            $post,
            $request->validated('method') ?? 'copy_link'
        );

        return response()->json([
            'success' => true,
            'shared' => $result['shared'],
            'shares_count' => $result['shares_count'],
            'url' => $result['url'],
        ]);
    }
}
