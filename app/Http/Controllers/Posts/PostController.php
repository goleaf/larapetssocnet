<?php

namespace App\Http\Controllers\Posts;

use App\Actions\Posts\ArchivePostAction;
use App\Actions\Posts\CreatePostAction;
use App\Actions\Posts\PinPostAction;
use App\Actions\Posts\PublishPostAction;
use App\Actions\Posts\SchedulePostAction;
use App\Actions\Posts\UnpinPostAction;
use App\Actions\Posts\UnpublishPostAction;
use App\Actions\Posts\UpdatePostAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\CreatePostRequest;
use App\Http\Requests\Posts\PublishPostRequest;
use App\Http\Requests\Posts\SchedulePostRequest;
use App\Http\Requests\Posts\UpdatePostRequest;
use App\Models\Content\Post;
use App\Models\Pets\Pet;
use App\Support\Posts\PostCreationInput;
use Carbon\CarbonImmutable;
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
        private readonly PublishPostAction $publishPostAction,
        private readonly SchedulePostAction $schedulePostAction,
        private readonly UnpublishPostAction $unpublishPostAction,
        private readonly ArchivePostAction $archivePostAction,
        private readonly PinPostAction $pinPostAction,
        private readonly UnpinPostAction $unpinPostAction,
    ) {}

    public function create(Request $request): View
    {
        $availablePets = $request->user()
            ?->pets()
            ->without(['user', 'species', 'breed', 'media', 'tags'])
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get() ?? collect();

        return view('posts.create', [
            'availablePets' => $availablePets,
        ]);
    }

    public function show(Request $request, Post $post): View
    {
        $this->authorize('view', $post);

        $viewerId = (int) ($request->user()?->getKey() ?? 0);

        $post->load([
            'user',
            'author',
            'author.media',
            'originalPost.author.media',
            'originalPost.postMedia',
            'quotePost.author.media',
            'quotePost.postMedia',
            'pet' => fn (BelongsTo $petQuery): BelongsTo => $petQuery->visibleTo($request->user()),
            'media',
            'tags',
        ]);

        $post->loadCount([
            'reactions as likes_count',
            'comments as comments_count',
        ]);

        $post->loadExists([
            'reactions as liked_by_viewer' => fn (Builder $reactionQuery): Builder => $reactionQuery->where('reactions.user_id', $viewerId),
            'savedBy as saved_by_viewer' => fn (Builder $savedQuery): Builder => $savedQuery->where('saved_posts.user_id', $viewerId),
        ]);

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
            'taggedPets' => $taggedPets,
        ]);
    }

    public function edit(Request $request, Post $post): View
    {
        Gate::authorize('update', $post);

        $availablePets = $request->user()
            ?->pets()
            ->without(['user', 'species', 'breed', 'media', 'tags'])
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get() ?? collect();

        return view('posts.edit', [
            'post' => $post,
            'availablePets' => $availablePets,
        ]);
    }

    public function store(CreatePostRequest $request): RedirectResponse
    {
        $result = $this->createPostAction->handle(
            user: $request->user(),
            input: PostCreationInput::fromUserInput($request->user(), [
                ...$request->safe()->except(['media', 'photos', 'video']),
                'media_files' => $request->mediaFiles(),
            ]),
        );

        if ($result->duplicateDetected) {
            return back()
                ->withInput()
                ->with('warning', 'You already posted this recently.')
                ->with('duplicate_post_id', $result->duplicatePostId);
        }

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

    public function pin(Request $request, Post $post): RedirectResponse
    {
        $this->pinPostAction->handle($request->user(), $post);

        return back()->with('success', 'Post pinned successfully.');
    }

    public function unpin(Request $request, Post $post): RedirectResponse
    {
        $this->unpinPostAction->handle($request->user(), $post);

        return back()->with('success', 'Post unpinned successfully.');
    }

    public function publish(PublishPostRequest $request, Post $post): RedirectResponse
    {
        $publishedAt = $request->validated()['published_at'] ?? null;

        $this->publishPostAction->handle($request->user(), $post, $publishedAt ? CarbonImmutable::parse($publishedAt) : null);

        return back()->with('success', 'Post published.');
    }

    public function schedule(SchedulePostRequest $request, Post $post): RedirectResponse
    {
        $publishedAt = $request->validated()['published_at'];

        $this->schedulePostAction->handle($request->user(), $post, CarbonImmutable::parse($publishedAt));

        return back()->with('success', 'Post scheduled.');
    }

    public function unpublish(Request $request, Post $post): RedirectResponse
    {
        $this->unpublishPostAction->handle($request->user(), $post);

        return back()->with('success', 'Post moved to drafts.');
    }

    public function archive(Request $request, Post $post): RedirectResponse
    {
        $this->archivePostAction->handle($request->user(), $post);

        return back()->with('success', 'Post archived.');
    }
}
