<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupPostRequest;
use App\Models\Group;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GroupPostController extends Controller
{
    public function store(StoreGroupPostRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('post', $group);

        /** @var array{body?: string, media?: array<int, UploadedFile>} $validated */
        $validated = $request->validated();

        $media = $request->file('media', []);
        $type = $this->resolveType($media);

        $post = new Post;
        $post->forceFill($this->filterPostPayload([
            'user_id' => $request->user()->getKey(),
            'group_id' => $group->getKey(),
            'body' => $validated['body'] ?? null,
            'type' => $type,
            'visibility' => Post::VISIBILITY_PUBLIC,
            'status' => 'published',
            'published_at' => now(),
        ]));
        $post->save();

        foreach ($media as $file) {
            if (str_starts_with((string) $file->getMimeType(), 'video/')) {
                $post->addMedia($file)->toMediaCollection('videos');

                continue;
            }

            $post->addMedia($file)->toMediaCollection('photos');
        }

        $group->attachSharedPost($post, (int) $request->user()->getKey());

        $this->syncPostsCount($group);

        return back()->with('status', 'Post published to the group.');
    }

    public function destroy(Request $request, Group $group, Post $post): RedirectResponse
    {
        $belongsToGroup = $this->postBelongsToGroup($post, $group);

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

        $this->syncPostsCount($group);

        return back()->with('status', 'Group post removed.');
    }

    private function resolveType(array $media): string
    {
        if ($media === []) {
            return Post::TYPE_TEXT;
        }

        foreach ($media as $file) {
            if (str_starts_with((string) $file->getMimeType(), 'video/')) {
                return Post::TYPE_VIDEO;
            }
        }

        return Post::TYPE_PHOTO;
    }

    private function postBelongsToGroup(Post $post, Group $group): bool
    {
        return $group->includesPost($post);
    }

    private function syncPostsCount(Group $group): void
    {
        $group->syncPostsCount();
    }

    private function filterPostPayload(array $payload): array
    {
        try {
            $columns = Schema::getColumnListing('posts');
        } catch (Throwable) {
            return $payload;
        }

        return collect($payload)
            ->only($columns)
            ->all();
    }
}
