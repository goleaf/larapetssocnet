<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupPostRequest;
use App\Models\Group;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

        if (Schema::hasTable('group_posts')) {
            DB::table('group_posts')->insertOrIgnore([
                'group_id' => $group->getKey(),
                'post_id' => $post->getKey(),
                'added_by_user_id' => $request->user()->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

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

        if (Schema::hasTable('group_posts')) {
            DB::table('group_posts')
                ->where('group_id', $group->getKey())
                ->where('post_id', $post->getKey())
                ->delete();
        }

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
        if (Schema::hasColumn('posts', 'group_id') && (int) $post->group_id === (int) $group->getKey()) {
            return true;
        }

        if (! Schema::hasTable('group_posts')) {
            return false;
        }

        return DB::table('group_posts')
            ->where('group_id', $group->getKey())
            ->where('post_id', $post->getKey())
            ->exists();
    }

    private function syncPostsCount(Group $group): void
    {
        if (! Schema::hasColumn('groups', 'posts_count')) {
            return;
        }

        $postIds = collect();

        if (Schema::hasColumn('posts', 'group_id')) {
            $postIds = $postIds->merge(
                DB::table('posts')->where('group_id', $group->getKey())->pluck('id')
            );
        }

        if (Schema::hasTable('group_posts')) {
            $postIds = $postIds->merge(
                DB::table('group_posts')->where('group_id', $group->getKey())->pluck('post_id')
            );
        }

        $group->forceFill([
            'posts_count' => $postIds->unique()->count(),
        ])->save();
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
