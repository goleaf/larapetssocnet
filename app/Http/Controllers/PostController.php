<?php

namespace App\Http\Controllers;

use App\Actions\Posts\CreatePostAction;
use App\Actions\Posts\UpdatePostAction;
use App\Http\Requests\CreatePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Pet;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PostController extends Controller
{
    public function __construct(
        private readonly CreatePostAction $createPostAction,
        private readonly UpdatePostAction $updatePostAction,
        private readonly PostService $posts,
    ) {}

    public function create(): View
    {
        return view('posts.create');
    }

    public function show(Request $request, Post $post): View
    {
        $this->authorize('view', $post);

        $viewerId = (int) ($request->user()?->getKey() ?? 0);

        $post->load([
            'user',
            'author',
            'pet' => fn (BelongsTo $petQuery): BelongsTo => $petQuery->visibleTo($request->user()),
            'media',
            'tags',
        ]);

        $post->loadCount([
            'likes',
            'comments',
        ]);

        $post->loadExists([
            'likes' => fn (Builder $likeQuery): Builder => $likeQuery->where('likes.user_id', $viewerId),
        ]);

        $comments = $post->comments()
            ->topLevel()
            ->with([
                'user',
                'replies.user',
            ])
            ->withCount('reactions')
            ->latest('comments.created_at')
            ->paginate(20)
            ->withQueryString();

        $taggedPetIds = collect($post->tagged_pets ?? [])
            ->filter()
            ->map(fn (mixed $petId): int => (int) $petId)
            ->filter(fn (int $petId): bool => $petId > 0)
            ->values();

        $taggedPets = $taggedPetIds->isEmpty()
            ? collect()
            : Pet::query()
                ->visibleTo($request->user())
                ->whereIn('id', $taggedPetIds)
                ->get();

        return view('posts.show', [
            'post' => $post,
            'comments' => $comments,
            'taggedPets' => $taggedPets,
        ]);
    }

    public function edit(Post $post): View
    {
        Gate::authorize('update', $post);

        return view('posts.edit', compact('post'));
    }

    public function store(CreatePostRequest $request): RedirectResponse
    {
        $this->createPostAction->handle(
            user: $request->user(),
            data: [
                ...$request->safe()->except(['media', 'photos', 'video']),
                'media_files' => $request->mediaFiles(),
            ],
        );

        return back()->with('success', __('feed.flash_post_created'));
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $this->updatePostAction->handle($request->user(), $post, $request->validated());

        return redirect()
            ->route('posts.show', $post)
            ->with('success', __('feed.flash_post_updated'));
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return back()->with('success', __('feed.flash_post_deleted'));
    }

    public function pin(Post $post): RedirectResponse
    {
        Gate::authorize('pin', $post);
        $this->posts->pin($post);

        return back()->with('success', 'Post pinned successfully.');
    }

    public function unpin(Post $post): RedirectResponse
    {
        Gate::authorize('pin', $post);
        $this->posts->unpin($post);

        return back()->with('success', 'Post unpinned successfully.');
    }
}
