<?php

namespace App\Http\Controllers\Posts;

use App\Actions\Engagement\TrackShareAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\ShareActionRequest;
use App\Models\Content\Post;
use Illuminate\Http\JsonResponse;

class ShareController extends Controller
{
    public function __construct(private readonly TrackShareAction $trackShare) {}

    public function store(ShareActionRequest $request, Post $post): JsonResponse
    {
        $this->authorize('share', $post);

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
