<?php

namespace App\Http\Controllers\Groups;

use App\Actions\Posts\CreatePostAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groups\StoreGroupPostRequest;
use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Services\GroupService;
use App\Services\GroupVisibilityService;
use App\Services\SyncGroupCountersService;
use Illuminate\Http\JsonResponse;
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

        $result = $action->handle($request->user(), $data);

        if ($result->duplicateDetected) {
            return back()
                ->withInput()
                ->with('warning', 'You already posted this recently.')
                ->with('duplicate_post_id', $result->duplicatePostId);
        }

        $post = $result->createdPost();

        $group->attachSharedPost($post, (int) $request->user()->getKey());

        $counters->syncPostsCount($group);

        return back()->with('status', 'Post published to the group.');
    }

    public function latest(Request $request, Group $group, GroupVisibilityService $visibility): JsonResponse
    {
        $this->authorize('view', $group);

        if (! $visibility->canViewGroupPosts($request->user(), $group)) {
            abort(403);
        }

        $afterId = max(0, (int) $request->integer('after_id'));
        $latestPostId = Post::query()
            ->inGroupFeed($group)
            ->visibleTo($request->user())
            ->latest('posts.created_at')
            ->value('posts.id');

        return response()->json([
            'latest_post_id' => $latestPostId,
            'has_new_posts' => $latestPostId !== null && (int) $latestPostId > $afterId,
        ]);
    }

    public function destroy(Request $request, Group $group, Post $post, SyncGroupCountersService $counters, GroupService $groups): RedirectResponse
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

        if ($canModerate) {
            $groups->notifyPostRemoved($request->user(), $group, $post);
        }

        return back()->with('status', 'Group post removed.');
    }
}
