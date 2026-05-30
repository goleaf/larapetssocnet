<?php

namespace App\Livewire\Comments;

use App\Models\Content\Comment;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Services\CommentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CommentReaction extends Component
{
    #[Locked]
    public int $commentId;

    public int $pawCount = 0;

    public int $loveCount = 0;

    public int $totalCount = 0;

    public ?string $currentReaction = null;

    public function mount(int $commentId): void
    {
        $this->commentId = $commentId;
        $this->loadReactionState();
    }

    public function react(string $type, CommentService $comments): void
    {
        $viewer = Auth::user();
        $comment = Comment::withTrashed()->whereKey($this->commentId)->firstOrFail();

        if (! $viewer instanceof User || $comment->trashed()) {
            return;
        }

        $type = Reaction::normalizeType($type);

        if (! in_array($type, Reaction::commentTypes(), true)) {
            return;
        }

        Gate::forUser($viewer)->authorize('react', $comment);

        $previousTotal = (int) $comment->reactions_count;
        $comments->toggleReaction($comment, $viewer, $type);
        $comment->refresh();
        $this->syncFromComment($comment);

        if ($previousTotal === 0 && $this->totalCount === 1 && $comment->created_at?->greaterThanOrEqualTo(now()->subDay())) {
            $this->dispatch('comment-first-reaction', commentId: $this->commentId);
        }
    }

    public function render(): View
    {
        return view('livewire.comments.comment-reaction');
    }

    private function loadReactionState(): void
    {
        $comment = Comment::query()->whereKey($this->commentId)->first();

        if (! $comment instanceof Comment) {
            return;
        }

        $this->syncFromComment($comment);
    }

    private function syncFromComment(Comment $comment): void
    {
        $this->pawCount = (int) $comment->paw_count;
        $this->loveCount = (int) $comment->love_count;
        $this->totalCount = (int) $comment->reactions_count;

        $viewer = Auth::user();

        $this->currentReaction = $viewer instanceof User
            ? $comment->reactionFrom($viewer)?->type
            : null;
    }
}
