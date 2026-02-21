<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Pet;
use App\Models\Post;
use App\Models\SavedPost;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function __construct(private readonly PostService $postService) {}

    public function create(Request $request): View
    {
        $this->authorize('create', Post::class);

        return view('posts.create', [
            'post' => new Post(['visibility' => Post::VISIBILITY_PUBLIC]),
            'pets' => $request->user()->pets()->orderBy('name')->get(['id', 'name', 'species']),
            'visibilityOptions' => Post::visibilityOptions(),
        ]);
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $post = $this->postService->create(
            author: $request->user(),
            data: $request->validated(),
            video: $request->file('video'),
            photos: $request->file('photos', []),
        );

        return redirect()->route('posts.show', $post)->with('success', 'Post created!');
    }

    public function show(Request $request, Post $post): View
    {
        $this->authorize('view', $post);

        $post->loadMissing([
            'author.media', 'pet.media', 'media', 'hashtags',
            'reactions',
            'comments' => fn ($q) => $q->whereNull('parent_id')->with(['user.media', 'reactions', 'replies.user.media'])->latest(),
        ]);

        $taggedPets = $this->resolveTaggedPets($post);

        $userReaction = null;
        $isSaved = false;

        if ($request->user()) {
            $userReaction = $post->postReactions()->where('user_id', $request->user()->id)->value('type');
            $isSaved = SavedPost::query()->where('post_id', $post->id)->where('user_id', $request->user()->id)->exists();
        }

        return view('posts.show', compact('post', 'taggedPets', 'userReaction', 'isSaved'));
    }

    public function edit(Request $request, Post $post): View
    {
        $this->authorize('update', $post);

        return view('posts.edit', [
            'post' => $post,
            'pets' => $request->user()->pets()->orderBy('name')->get(['id', 'name', 'species']),
            'visibilityOptions' => Post::visibilityOptions(),
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $this->postService->update($post, $request->validated());

        return redirect()->route('posts.show', $post)->with('success', 'Post updated.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $this->postService->delete($post);

        return redirect()->route('profile.show', $request->user()->username)->with('success', 'Post deleted.');
    }

    public function pin(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        $this->authorize('pin', $post);

        $wasPinned = (bool) $post->is_pinned;

        if ($wasPinned) {
            $this->postService->unpin($post);
        } else {
            $this->postService->pin($post);
        }

        $isPinned = ! $wasPinned;

        if (! $request->expectsJson()) {
            return back()->with('status', $isPinned ? 'Post pinned on profile.' : 'Post unpinned.');
        }

        return response()->json([
            'success' => true,
            'is_pinned' => $isPinned,
        ]);
    }

    public function unpin(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        $this->authorize('pin', $post);

        $this->postService->unpin($post);

        if (! $request->expectsJson()) {
            return back()->with('status', 'Post unpinned.');
        }

        return response()->json([
            'success' => true,
            'is_pinned' => false,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Pet>
     */
    private function resolveTaggedPets(Post $post)
    {
        $petIds = collect($post->tagged_pets ?? [])->map(fn ($id): int => (int) $id)->filter()->values();

        if ($petIds->isEmpty() && $post->pet_id) {
            $petIds = collect([(int) $post->pet_id]);
        }

        if ($petIds->isEmpty()) {
            return collect();
        }

        return Pet::query()->whereIn('id', $petIds)->orderBy('name')->get(['id', 'name']);
    }
}
