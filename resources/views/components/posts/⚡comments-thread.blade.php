<?php

use App\Actions\Comments\CreateCommentAction;
use App\Actions\Comments\DeleteCommentAction;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\CommentService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public Post $post;

    public string $body = '';

    /**
     * @var array<int, string>
     */
    public array $replyBodies = [];

    public int $commentCount = 0;

    public string $threadFingerprint = '';

    public bool $hasFreshActivity = false;

    public ?string $statusMessage = null;

    public function mount(Post $post): void
    {
        $this->post = $post;
        $this->syncThreadState();
    }

    /**
     * @return Collection<int, Comment>
     */
    public function comments(): Collection
    {
        return app(CommentService::class)->previewThread($this->post, $this->viewer(), 5);
    }

    public function createComment(CreateCommentAction $action): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $body = $this->validatedInlineBody($this->body, 'body');

        if ($body === null) {
            return;
        }

        try {
            $action->handle($viewer, $this->post, $body);
        } catch (ValidationException $exception) {
            $this->copyValidationErrors($exception, 'body');

            return;
        }

        $this->body = '';
        $this->statusMessage = 'Comment posted.';
        $this->syncThreadState();
        $this->dispatchThreadUpdated();
    }

    public function createReply(int $parentId, CreateCommentAction $action): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $errorKey = 'replyBodies.'.$parentId;
        $body = $this->validatedInlineBody((string) ($this->replyBodies[$parentId] ?? ''), $errorKey);

        if ($body === null) {
            return;
        }

        try {
            $action->handle($viewer, $this->post, $body, $parentId);
        } catch (ValidationException $exception) {
            $this->copyValidationErrors($exception, $errorKey);

            return;
        }

        unset($this->replyBodies[$parentId]);

        $this->statusMessage = 'Reply posted.';
        $this->syncThreadState();
        $this->dispatchThreadUpdated();
    }

    public function deleteComment(int $commentId, DeleteCommentAction $action): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $comment = Comment::query()
            ->where('comments.post_id', $this->post->getKey())
            ->whereKey($commentId)
            ->firstOrFail();

        $action->handle($viewer, $comment);

        $this->statusMessage = 'Comment removed.';
        $this->syncThreadState();
        $this->dispatchThreadUpdated();
    }

    public function refreshThread(): void
    {
        $previousFingerprint = $this->threadFingerprint;

        $this->syncThreadState();

        if ($previousFingerprint !== '' && $previousFingerprint !== $this->threadFingerprint) {
            $this->hasFreshActivity = true;
            $this->dispatchThreadUpdated();
        }
    }

    private function viewer(): ?User
    {
        $viewer = auth()->user();

        return $viewer instanceof User ? $viewer : null;
    }

    private function validatedInlineBody(string $body, string $errorKey): ?string
    {
        $body = trim($body);

        $this->resetErrorBag($errorKey);

        if ($body === '') {
            $this->addError($errorKey, 'Please write something before submitting.');

            return null;
        }

        if (mb_strlen($body) > CommentService::MAX_BODY_LENGTH) {
            $this->addError($errorKey, 'Comments may not be longer than 1000 characters.');

            return null;
        }

        return $body;
    }

    private function copyValidationErrors(ValidationException $exception, string $fallbackKey): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $key = $field === 'parent_id' ? $fallbackKey : $field;

            foreach ($messages as $message) {
                $this->addError($key, $message);
            }
        }
    }

    private function syncThreadState(): void
    {
        $commentCount = Post::query()
            ->whereKey($this->post->getKey())
            ->value('comments_count');

        $this->commentCount = (int) ($commentCount ?? 0);
        $this->post->forceFill(['comments_count' => $this->commentCount]);

        $activity = app(CommentService::class)->threadActivity($this->post, $this->viewer());
        $this->threadFingerprint = implode(':', [
            $activity['visible_count'],
            $activity['latest_id'],
            $activity['latest_updated_at'] ?? 'none',
        ]);
    }

    private function dispatchThreadUpdated(): void
    {
        $this->dispatch('post-card-refresh', postId: (int) $this->post->getKey());
        $this->dispatch('comments-thread-updated', postId: (int) $this->post->getKey(), commentsCount: $this->commentCount);
    }
};
?>

<x-ui.card padding="base" wire:poll.visible.15s="refreshThread" data-ui="comments-thread">
    @php($comments = $this->comments())

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-bold text-bark">Comments <span class="font-semibold text-fur">({{ number_format($commentCount) }})</span></p>
            <p class="mt-0.5 text-xs text-fur" aria-live="polite">
                @if ($hasFreshActivity)
                    Thread refreshed just now.
                @else
                    Live updates check while this thread is visible.
                @endif
            </p>
        </div>
        <a href="{{ route('posts.show', $post) }}#comments" class="text-xs font-semibold text-paw hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
            View full thread
        </a>
    </div>

    @auth
        @can('create', [Comment::class, $post])
            <form wire:submit.prevent="createComment" class="mb-5 flex items-start gap-3" data-ui="comments-thread-form">
                <x-ui.avatar :src="auth()->user()?->avatar_url" :name="auth()->user()?->name" :user="auth()->user()" size="sm" class="mt-1"/>
                <div class="min-w-0 flex-1">
                    <textarea
                        wire:model.live.debounce.300ms="body"
                        rows="2"
                        maxlength="{{ CommentService::MAX_BODY_LENGTH }}"
                        class="form-textarea min-h-11 w-full resize-none overflow-hidden py-2.5 text-sm"
                        placeholder="Write a comment..."
                        required
                        oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"
                    ></textarea>
                    @error('body')
                        <p class="mt-1 text-xs font-semibold text-rose">{{ $message }}</p>
                    @enderror
                    <div class="mt-2 flex items-center justify-between gap-3">
                        <p class="text-xs text-fur" aria-live="polite">
                            <span wire:loading.remove wire:target="createComment">{{ $statusMessage }}</span>
                            <span wire:loading wire:target="createComment">Posting comment...</span>
                        </p>
                        <x-ui.button type="submit" size="sm" variant="primary" wire:loading.attr="disabled" wire:target="createComment">
                            Post Comment
                        </x-ui.button>
                    </div>
                </div>
            </form>
        @endcan
    @else
        <div class="mb-5 rounded-[var(--radius-soft)] border border-whisker/40 bg-cream/50 p-3 text-sm text-fur">
            <a href="{{ route('login') }}" class="font-semibold text-paw hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Log in</a>
            to join the conversation.
        </div>
    @endauth

    @forelse ($comments as $comment)
        @if ($loop->first)
            <div class="space-y-4">
        @endif
            <x-comment-item :comment="$comment" :post="$post" :livewire="true"/>
        @if ($loop->last)
            </div>
        @endif
    @empty
        <p class="text-sm text-fur">No comments yet.</p>
    @endforelse
</x-ui.card>
