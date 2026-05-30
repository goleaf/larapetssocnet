<?php

namespace App\Livewire\Comments;

use App\Actions\Comments\DeleteCommentAction;
use App\Actions\Comments\UpdateCommentAction;
use App\Models\Content\Comment;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class CommentCard extends Component
{
    #[Locked]
    public int $commentId;

    #[Locked]
    public int $postId;

    public bool $isPinned = false;

    public bool $viewerCanPin = false;

    public ?Comment $comment = null;

    public bool $isReplyComposerOpen = false;

    public bool $isEditMode = false;

    public string $editContent = '';

    /**
     * @var list<array{id: int}>
     */
    public array $replies = [];

    public bool $showAllReplies = false;

    public bool $isLoadingMoreReplies = false;

    public bool $noMoreReplies = false;

    public int $replyCount = 0;

    public bool $showImpact = false;

    public function mount(int $commentId, int $postId, bool $isPinned = false, bool $viewerCanPin = false): void
    {
        $this->commentId = $commentId;
        $this->postId = $postId;
        $this->isPinned = $isPinned;
        $this->viewerCanPin = $viewerCanPin;
        $this->loadComment();
        $this->loadPreviewReplies();
    }

    public function openReplyComposer(): void
    {
        $this->isReplyComposerOpen = true;
        $this->dispatch('focus-reply-composer-'.$this->commentId);
    }

    public function closeReplyComposer(): void
    {
        $this->isReplyComposerOpen = false;
    }

    public function openEditMode(): void
    {
        if (! $this->canEdit()) {
            return;
        }

        $this->isEditMode = true;
        $this->editContent = (string) $this->comment?->body;
    }

    public function saveEdit(UpdateCommentAction $action): void
    {
        if (! $this->canEdit() || ! $this->comment instanceof Comment) {
            return;
        }

        $validated = $this->validate([
            'editContent' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        $this->comment = $action->handle(Auth::user(), $this->comment, (string) $validated['editContent']);
        $this->isEditMode = false;
        $this->editContent = '';
        $this->dispatch('$refresh');
    }

    public function cancelEdit(): void
    {
        $this->isEditMode = false;
        $this->editContent = '';
    }

    public function deleteComment(DeleteCommentAction $action): void
    {
        $viewer = Auth::user();

        if (! $viewer instanceof User || ! $this->comment instanceof Comment) {
            return;
        }

        $action->handle($viewer, $this->comment);
        $this->loadComment();

        if (! $this->hasVisibleReplies()) {
            $this->dispatch('comment-deleted', commentId: $this->commentId);

            return;
        }

        $this->dispatch('$refresh');
    }

    public function pinComment(): void
    {
        $viewer = Auth::user();

        if (! $viewer instanceof User || ! $this->comment instanceof Comment || ! $this->viewerCanPin) {
            return;
        }

        Gate::forUser($viewer)->authorize('pin', $this->comment);

        DB::transaction(function (): void {
            Comment::query()
                ->where('post_id', $this->postId)
                ->where('is_pinned', true)
                ->update(['is_pinned' => false]);

            Comment::query()
                ->whereKey($this->commentId)
                ->update(['is_pinned' => true]);
        });

        $this->isPinned = true;
        $this->dispatch('comment-pinned', postId: $this->postId, commentId: $this->commentId);
    }

    public function unpinComment(): void
    {
        $viewer = Auth::user();

        if (! $viewer instanceof User || ! $this->comment instanceof Comment || ! $this->viewerCanPin) {
            return;
        }

        Gate::forUser($viewer)->authorize('pin', $this->comment);

        $this->comment->forceFill(['is_pinned' => false])->save();
        $this->isPinned = false;
        $this->dispatch('comment-pinned', postId: $this->postId, commentId: 0);
    }

    public function loadMoreReplies(): void
    {
        if (! $this->comment instanceof Comment || $this->isLoadingMoreReplies || $this->noMoreReplies) {
            return;
        }

        $this->isLoadingMoreReplies = true;
        $loadedIds = collect($this->replies)->pluck('id')->all();
        $replies = $this->replyQuery()
            ->whereNotIn('comments.id', $loadedIds)
            ->oldest('comments.created_at')
            ->limit(5)
            ->get(['comments.id']);

        $this->replies = [
            ...$this->replies,
            ...$this->replyReferences($replies),
        ];
        $this->showAllReplies = true;
        $this->noMoreReplies = $replies->count() < 5;
        $this->isLoadingMoreReplies = false;
    }

    public function collapseReplies(): void
    {
        $this->replies = array_slice($this->replies, 0, 2);
        $this->showAllReplies = false;
        $this->noMoreReplies = false;
    }

    #[On('reply-created')]
    public function onReplyCreated(int $parentCommentId, int $commentId): void
    {
        if ($parentCommentId !== $this->commentId) {
            return;
        }

        $this->replies = [
            ['id' => $commentId],
            ...collect($this->replies)
                ->reject(fn (array $reply): bool => (int) $reply['id'] === $commentId)
                ->values()
                ->all(),
        ];
        $this->replyCount++;
        $this->isReplyComposerOpen = false;
    }

    #[On('comment-first-reaction')]
    public function onFirstReaction(int $commentId): void
    {
        if ($commentId !== $this->commentId || ! $this->comment instanceof Comment) {
            return;
        }

        $viewer = Auth::user();

        if (! $viewer instanceof User || (int) $viewer->getKey() !== (int) $this->comment->user_id) {
            return;
        }

        if ($this->comment->created_at?->lessThan(now()->subDay())) {
            return;
        }

        $this->showImpact = true;
    }

    #[Computed]
    public function canEdit(): bool
    {
        $viewer = Auth::user();

        return $viewer instanceof User
            && $this->comment instanceof Comment
            && (int) $this->comment->user_id === (int) $viewer->getKey()
            && $this->comment->created_at?->greaterThanOrEqualTo(now()->subHour());
    }

    #[Computed]
    public function canDelete(): bool
    {
        $viewer = Auth::user();

        return $viewer instanceof User
            && $this->comment instanceof Comment
            && (int) $this->comment->user_id === (int) $viewer->getKey();
    }

    #[Computed]
    public function isDeleted(): bool
    {
        return $this->comment?->trashed() ?? false;
    }

    #[Computed]
    public function hasVisibleReplies(): bool
    {
        if (! $this->comment instanceof Comment) {
            return false;
        }

        return Comment::query()
            ->where('parent_id', $this->commentId)
            ->whereNull('deleted_at')
            ->visibleTo(Auth::user())
            ->exists();
    }

    #[Computed]
    public function timeAgo(): string
    {
        return $this->comment?->created_at?->diffForHumans() ?? '';
    }

    #[Computed]
    public function editedLabel(): ?string
    {
        return $this->comment?->edited_at ? 'Edited' : null;
    }

    #[Computed]
    public function isBlocked(): bool
    {
        $viewer = Auth::user();

        if (! $viewer instanceof User || ! $this->comment instanceof Comment) {
            return false;
        }

        $this->comment->loadMissing('user');
        $author = $this->comment->user;

        return $author instanceof User && $viewer->hasBlockingRelationshipWith($author);
    }

    #[Computed]
    public function indentationClasses(): string
    {
        if (! $this->comment instanceof Comment || (int) $this->comment->depth < 1) {
            return '';
        }

        return 'relative ml-3 border-l-2 border-fur/25 pl-3';
    }

    #[Computed]
    public function shouldRender(): bool
    {
        if (! $this->comment instanceof Comment || $this->isBlocked()) {
            return false;
        }

        return ! $this->isDeleted() || $this->hasVisibleReplies();
    }

    #[Computed]
    public function authorProfileUrl(): string
    {
        $username = (string) ($this->comment?->user?->username ?? '');

        return $username === '' ? '#' : route('profile.show', ['user' => $username]);
    }

    #[Computed]
    public function authorAvatarUrl(): ?string
    {
        $avatar = $this->comment?->user?->avatar_url;

        return is_string($avatar) && $avatar !== '' ? $avatar : null;
    }

    #[Computed]
    public function authorInitial(): string
    {
        return mb_substr((string) ($this->comment?->user?->name ?? '?'), 0, 1);
    }

    #[Computed]
    public function reactionDefinitions(): array
    {
        return collect(Reaction::commentTypes())
            ->map(fn (string $type): array => [
                'type' => $type,
                'label' => Reaction::labelMap()[$type] ?? ucfirst($type),
                'emoji' => Reaction::emojiMap()[$type] ?? '',
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.comments.comment-card');
    }

    private function loadComment(): void
    {
        $this->comment = Comment::withTrashed()
            ->with([
                'user' => fn ($query) => $query->select([
                    'users.id',
                    'users.name',
                    'users.username',
                    'users.avatar_path',
                    'users.profile_photo_path',
                ]),
            ])
            ->whereKey($this->commentId)
            ->firstOrFail();
    }

    private function loadPreviewReplies(): void
    {
        if (! $this->comment instanceof Comment || (int) $this->comment->depth >= 2) {
            $this->replies = [];
            $this->replyCount = 0;
            $this->noMoreReplies = true;

            return;
        }

        $this->replyCount = Comment::query()
            ->where('parent_id', $this->commentId)
            ->whereNull('deleted_at')
            ->visibleTo(Auth::user())
            ->count();

        $replies = $this->replyQuery()
            ->latest('comments.created_at')
            ->limit(2)
            ->get(['comments.id']);

        $this->replies = $this->replyReferences($replies);
        $this->noMoreReplies = $this->replyCount <= count($this->replies);
    }

    private function replyQuery(): Builder
    {
        return Comment::withTrashed()
            ->where('comments.post_id', $this->postId)
            ->where('comments.parent_id', $this->commentId)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('comments.deleted_at')
                    ->orWhere('comments.replies_count', '>', 0);
            })
            ->visibleTo(Auth::user());
    }

    /**
     * @param  iterable<int, Comment>  $replies
     * @return list<array{id: int}>
     */
    private function replyReferences(iterable $replies): array
    {
        return collect($replies)
            ->map(fn (Comment $comment): array => ['id' => (int) $comment->getKey()])
            ->values()
            ->all();
    }
}
