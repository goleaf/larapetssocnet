<?php

namespace App\Livewire\Comments;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class CommentSection extends Component
{
    #[Locked]
    public int $postId;

    public bool $isMounted = false;

    /**
     * @var list<array{id: int}>
     */
    public array $comments = [];

    /**
     * @var array{id: int}|null
     */
    public ?array $pinnedComment = null;

    public string $sortMode = 'newest';

    public bool $showNewCommentIndicator = false;

    public int $newCommentCount = 0;

    public ?int $lastLoadedCommentId = null;

    public bool $isLoadingMore = false;

    public bool $noMoreComments = false;

    public int $totalCommentCount = 0;

    public string $searchQuery = '';

    public bool $isSearchMode = false;

    public function mount(int $postId): void
    {
        $this->postId = $postId;
        $this->isMounted = false;
        $this->totalCommentCount = (int) Post::query()
            ->whereKey($postId)
            ->value('comments_count');
    }

    public function openSection(): void
    {
        if ($this->isMounted) {
            return;
        }

        $this->isMounted = true;
        $this->loadInitialComments();
    }

    public function loadInitialComments(): void
    {
        if (! $this->isMounted) {
            return;
        }

        $this->pinnedComment = $this->pinnedCommentReference();

        if ($this->isSearchMode) {
            $this->loadSearchResults();

            return;
        }

        $query = $this->baseTopLevelQuery();
        $this->applySort($query);

        $comments = $query->limit(5)->get(['comments.id', 'comments.created_at']);

        $this->comments = $this->commentReferences($comments);
        $this->noMoreComments = $comments->count() < 5;
        $this->lastLoadedCommentId = $this->latestLoadedCommentId();
    }

    public function loadMore(): void
    {
        if ($this->isLoadingMore || $this->noMoreComments || $this->isSearchMode) {
            return;
        }

        $this->isLoadingMore = true;

        $loadedIds = collect($this->comments)->pluck('id')->all();
        $query = $this->baseTopLevelQuery()
            ->whereNotIn('comments.id', $loadedIds);
        $this->applySort($query);

        $comments = $query->limit(5)->get(['comments.id', 'comments.created_at']);

        $this->comments = [
            ...$this->comments,
            ...$this->commentReferences($comments),
        ];
        $this->noMoreComments = $comments->count() < 5;
        $this->lastLoadedCommentId = $this->latestLoadedCommentId();
        $this->isLoadingMore = false;
    }

    public function switchSort(string $mode): void
    {
        if (! in_array($mode, ['newest', 'oldest', 'top'], true)) {
            return;
        }

        $this->sortMode = $mode;
        $this->comments = [];
        $this->noMoreComments = false;
        $this->loadInitialComments();
    }

    public function pollForNewComments(): void
    {
        if (! $this->isMounted || $this->lastLoadedCommentId === null) {
            return;
        }

        $anchorCreatedAt = Comment::query()
            ->whereKey($this->lastLoadedCommentId)
            ->value('created_at');

        if ($anchorCreatedAt === null) {
            return;
        }

        $count = Comment::query()
            ->where('comments.post_id', $this->postId)
            ->where('comments.depth', 0)
            ->whereNull('comments.parent_id')
            ->whereNull('comments.deleted_at')
            ->where('comments.created_at', '>', $anchorCreatedAt)
            ->count();

        $this->showNewCommentIndicator = $count > 0;
        $this->newCommentCount = $count;
    }

    public function loadNewComments(): void
    {
        if ($this->lastLoadedCommentId === null) {
            $this->loadInitialComments();

            return;
        }

        $anchorCreatedAt = Comment::query()
            ->whereKey($this->lastLoadedCommentId)
            ->value('created_at');

        if ($anchorCreatedAt === null) {
            return;
        }

        $comments = $this->baseTopLevelQuery()
            ->where('comments.created_at', '>', $anchorCreatedAt)
            ->latest('comments.created_at')
            ->get(['comments.id', 'comments.created_at']);

        $this->comments = [
            ...$this->commentReferences($comments),
            ...$this->comments,
        ];
        $this->lastLoadedCommentId = $this->latestLoadedCommentId();
        $this->showNewCommentIndicator = false;
        $this->newCommentCount = 0;
        $this->syncTotalCommentCount();
    }

    public function updatedSearchQuery(): void
    {
        $this->searchQuery = trim($this->searchQuery);
        $this->isSearchMode = $this->searchQuery !== '';

        if ($this->isSearchMode) {
            $this->loadSearchResults();

            return;
        }

        $this->comments = [];
        $this->noMoreComments = false;
        $this->loadInitialComments();
    }

    public function loadSearchResults(): void
    {
        $term = trim($this->searchQuery);

        if ($term === '') {
            $this->isSearchMode = false;
            $this->loadInitialComments();

            return;
        }

        $likeTerm = '%'.addcslashes($term, '%_\\').'%';
        $comments = Comment::query()
            ->where('comments.post_id', $this->postId)
            ->whereNull('comments.deleted_at')
            ->where('comments.body', 'like', $likeTerm)
            ->visibleTo(Auth::user())
            ->orderByDesc('comments.quality_score')
            ->latest('comments.created_at')
            ->limit(50)
            ->get(['comments.id', 'comments.created_at']);

        $this->comments = $this->commentReferences($comments);
        $this->noMoreComments = true;
    }

    #[On('comment-created')]
    public function onCommentCreated(int $postId, int $commentId): void
    {
        if ($postId !== $this->postId) {
            return;
        }

        $comment = Comment::query()
            ->whereKey($commentId)
            ->whereNull('parent_id')
            ->first(['id']);

        if (! $comment instanceof Comment) {
            $this->syncTotalCommentCount();

            return;
        }

        $this->comments = [
            ['id' => (int) $comment->getKey()],
            ...collect($this->comments)
                ->reject(fn (array $item): bool => (int) $item['id'] === (int) $comment->getKey())
                ->values()
                ->all(),
        ];
        $this->lastLoadedCommentId = $this->latestLoadedCommentId();
        $this->syncTotalCommentCount();
    }

    #[On('comment-pinned')]
    public function onCommentPinned(int $postId, int $commentId): void
    {
        if ($postId !== $this->postId) {
            return;
        }

        $this->pinnedComment = $commentId > 0 ? ['id' => $commentId] : null;
    }

    #[On('comment-deleted')]
    public function onCommentDeleted(int $commentId): void
    {
        $this->comments = collect($this->comments)
            ->reject(fn (array $comment): bool => (int) $comment['id'] === $commentId)
            ->values()
            ->all();
        $this->syncTotalCommentCount();
    }

    #[Computed]
    public function isAuthenticated(): bool
    {
        return Auth::check();
    }

    #[Computed]
    public function viewerCanPin(): bool
    {
        $viewer = Auth::user();

        if (! $viewer) {
            return false;
        }

        return Post::query()
            ->whereKey($this->postId)
            ->where('user_id', $viewer->getKey())
            ->exists();
    }

    #[Computed]
    public function showSearchInput(): bool
    {
        return $this->totalCommentCount > 50;
    }

    #[Computed]
    public function highlightedSearchQuery(): string
    {
        return e($this->searchQuery);
    }

    public function render(): View
    {
        return view('livewire.comments.comment-section');
    }

    private function baseTopLevelQuery(): Builder
    {
        return Comment::withTrashed()
            ->where('comments.post_id', $this->postId)
            ->where('comments.depth', 0)
            ->whereNull('comments.parent_id')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('comments.deleted_at')
                    ->orWhere('comments.replies_count', '>', 0);
            })
            ->visibleTo(Auth::user());
    }

    private function applySort(Builder $query): Builder
    {
        return match ($this->sortMode) {
            'oldest' => $query->oldest('comments.created_at'),
            'top' => $query->orderByDesc('comments.quality_score')->latest('comments.created_at'),
            default => $query->latest('comments.created_at'),
        };
    }

    /**
     * @param  iterable<int, Comment>  $comments
     * @return list<array{id: int}>
     */
    private function commentReferences(iterable $comments): array
    {
        return collect($comments)
            ->map(fn (Comment $comment): array => ['id' => (int) $comment->getKey()])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int}|null
     */
    private function pinnedCommentReference(): ?array
    {
        $commentId = Comment::query()
            ->where('comments.post_id', $this->postId)
            ->where('comments.is_pinned', true)
            ->whereNull('comments.deleted_at')
            ->value('id');

        return $commentId === null ? null : ['id' => (int) $commentId];
    }

    private function latestLoadedCommentId(): ?int
    {
        $ids = collect($this->comments)
            ->pluck('id')
            ->push($this->pinnedComment['id'] ?? null)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return null;
        }

        $comment = Comment::query()
            ->whereIn('id', $ids->all())
            ->latest('created_at')
            ->first(['id']);

        return $comment instanceof Comment ? (int) $comment->getKey() : null;
    }

    private function syncTotalCommentCount(): void
    {
        $this->totalCommentCount = (int) Post::query()
            ->whereKey($this->postId)
            ->value('comments_count');
    }
}
