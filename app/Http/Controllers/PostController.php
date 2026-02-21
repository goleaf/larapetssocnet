<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Pet;
use App\Models\Post;
use App\Models\SavedPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PostController extends Controller
{
    public function create(Request $request): View
    {
        return view('posts.create', [
            'post' => new Post([
                'visibility' => Post::VISIBILITY_PUBLIC,
            ]),
            'pets' => $request->user()->pets()->orderBy('name')->get(['id', 'name']),
            'visibilityOptions' => Post::visibilityOptions(),
        ]);
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $viewer = $request->user();
        $validated = $request->validated();

        $post = DB::transaction(function () use ($validated, $viewer, $request): Post {
            $post = Post::query()->create([
                'user_id' => $viewer->id,
                'body' => $validated['body'] ?? null,
                'visibility' => $validated['visibility'],
                'location' => $validated['location'] ?? null,
                'tagged_pets' => $this->normalizedTaggedPets($validated['tagged_pets'] ?? []),
                'type' => Post::TYPE_TEXT,
            ]);

            $this->syncMediaFromRequest($post, $request);
            $post->syncHashtagsFromBody();
            $post->updateTypeFromMedia();

            return $post->refresh();
        });

        return redirect()
            ->route('posts.show', $post)
            ->with('status', 'Post created.');
    }

    public function show(Request $request, Post $post): View
    {
        $viewer = $request->user();

        abort_unless($post->canBeViewedBy($viewer), 403);

        $post->load([
            'user',
            'hashtags',
            'topLevelComments.user',
            'topLevelComments.replies.user',
        ]);

        $taggedPets = $this->resolveTaggedPets($post);

        $userReaction = null;
        $isSaved = false;

        if ($viewer) {
            $userReaction = $post->postReactions()
                ->where('user_id', $viewer->id)
                ->value('type');

            $isSaved = SavedPost::query()
                ->where('post_id', $post->id)
                ->where('user_id', $viewer->id)
                ->exists();
        }

        return view('posts.show', [
            'post' => $post,
            'taggedPets' => $taggedPets,
            'userReaction' => $userReaction,
            'isSaved' => $isSaved,
        ]);
    }

    public function edit(Request $request, Post $post): View
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        return view('posts.edit', [
            'post' => $post,
            'pets' => $request->user()->pets()->orderBy('name')->get(['id', 'name']),
            'visibilityOptions' => Post::visibilityOptions(),
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        $validated = $request->validated();

        DB::transaction(function () use ($post, $validated, $request): void {
            $post->update([
                'body' => $validated['body'] ?? null,
                'visibility' => $validated['visibility'],
                'location' => $validated['location'] ?? null,
                'tagged_pets' => $this->normalizedTaggedPets($validated['tagged_pets'] ?? []),
            ]);

            $this->syncMediaFromRequest($post, $request, isUpdate: true);
            $post->syncHashtagsFromBody();
            $post->updateTypeFromMedia();
        });

        return redirect()
            ->route('posts.show', $post)
            ->with('status', 'Post updated.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        $post->delete();

        return redirect()
            ->route('feed.index')
            ->with('status', 'Post deleted.');
    }

    private function syncMediaFromRequest(Post $post, Request $request, bool $isUpdate = false): void
    {
        if ($isUpdate && $request->boolean('remove_photos')) {
            $post->clearMediaCollection('photos');
            $post->clearMediaCollection('images');
        }

        if ($isUpdate && $request->boolean('remove_video')) {
            $post->clearMediaCollection('video');
        }

        if ($request->hasFile('photos')) {
            $post->clearMediaCollection('video');
            $post->clearMediaCollection('photos');
            $post->clearMediaCollection('images');

            foreach ((array) $request->file('photos') as $photo) {
                $post->addMedia($photo)->toMediaCollection('photos');
            }
        }

        if ($request->hasFile('video')) {
            $post->clearMediaCollection('photos');
            $post->addMedia($request->file('video'))->toMediaCollection('video');
        }
    }

    /**
     * @param  array<int, mixed>  $taggedPets
     * @return array<int, int>
     */
    private function normalizedTaggedPets(array $taggedPets): array
    {
        return collect($taggedPets)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Pet>
     */
    private function resolveTaggedPets(Post $post)
    {
        $petIds = collect($post->tagged_pets ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values();

        if ($petIds->isEmpty()) {
            return collect();
        }

        return Pet::query()
            ->whereIn('id', $petIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
