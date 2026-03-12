<?php

namespace App\Http\Controllers;

use App\Actions\Posts\CreatePostAction;
use App\Http\Requests\StoreGroupPostRequest;
use App\Models\Group;
use App\Models\Post;
use App\Services\SyncGroupCountersService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupPostController extends Controller
{
    public function store(StoreGroupPostRequest $request, Group $group, CreatePostAction $action, SyncGroupCountersService $counters): RedirectResponse
    {
        $this->authorize('post', $group);

        $validated = $request->validated();

        if (isset($validated['post_id'])) {
            $post = Post::query()->whereKey((int) $validated['post_id'])->firstOrFail();
            $this->authorize('update', $post);

            if (! $group->includesPost($post)) {
                $group->attachSharedPost($post, (int) $request->user()->getKey());
                $counters->syncPostsCount($group);
            }

            return back()->with('status', 'Post shared to the group.');
        }

        $data = [
            'body' => $validated['body'] ?? null,
            'media_files' => $request->file('media', []),
            'visibility' => Post::VISIBILITY_PUBLIC,
            'status' => 'published',
            'group_id' => $group->getKey(),
        ];

        $action->handle($request->user(), $data);

        $counters->syncPostsCount($group);

        return back()->with('status', 'Post published to the group.');
    }

    public function destroy(Request $request, Group $group, Post $post, SyncGroupCountersService $counters): RedirectResponse
    {
        $belongsToGroup = $group->includesPost($post);

        if (! $belongsToGroup) {
            abort(404);
        }

        $isOwner = (int) $post->user_id === (int) $request->user()->getKey();
        $canModerate = $request->user()->can('moderate', $group);

        if (! $isOwner && ! $canModerate) {
            abort(403);
        }

        $post->delete();

        $group->detachSharedPost($post);

        $counters->syncPostsCount($group);

        return back()->with('status', 'Group post removed.');
    }
}
