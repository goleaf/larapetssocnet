<?php

namespace App\Http\Controllers\Posts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Moderation\StorePostReportRequest;
use App\Models\Content\Post;
use App\Models\Content\PostReport;
use Illuminate\Http\RedirectResponse;

class PostReportController extends Controller
{
    public function store(StorePostReportRequest $request, Post $post): RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($post->canBeViewedBy($viewer), 403);

        if ($post->user_id === $viewer->id) {
            return back()->withErrors(['report' => 'You cannot report your own post.']);
        }

        $validated = $request->validated();

        PostReport::query()->updateOrCreate(
            [
                'post_id' => $post->id,
                'user_id' => $viewer->id,
            ],
            [
                'reason' => $validated['reason'],
                'details' => $validated['details'] ?? null,
            ]
        );

        return back()->with('status', 'Post reported. Thank you.');
    }
}
