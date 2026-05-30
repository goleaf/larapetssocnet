<?php

use App\Actions\Comments\CreateCommentAction;
use App\Actions\Comments\DeleteCommentAction;
use App\Actions\Comments\UpdateCommentAction;
use App\Actions\Engagement\CreateReportAction;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
use App\Services\BlockService;
use App\Services\CommentDraftService;
use App\Services\CommentGifService;
use App\Services\CommentService;
use App\Services\CommentThreadSubscriptionService;
use App\Services\CommentTranslationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public Post $post;

    public bool $fullPage = false;

    public string $sort = 'oldest';

    public string $body = '';

    public string $search = '';

    public bool $draftRestored = false;

    /**
     * @var array{gif_url: string, gif_preview_url: ?string, gif_title: ?string, gif_provider: ?string}|null
     */
    public ?array $selectedGif = null;

    public bool $gifPickerOpen = false;

    public string $gifSearch = '';

    /**
     * @var list<array{id: string, title: string, gif_url: string, gif_preview_url: ?string, gif_provider: string}>
     */
    public array $gifResults = [];

    /**
     * @var array<int, string>
     */
    public array $translations = [];

    /**
     * @var array<int, string>
     */
    public array $replyBodies = [];

    /**
     * @var array<int, string>
     */
    public array $editBodies = [];

    public ?int $editingCommentId = null;

    public ?int $reportingCommentId = null;

    public string $reportReason = Report::REASON_SPAM;

    public string $reportDetails = '';

    public ?string $mentionTarget = null;

    public string $mentionSearch = '';

    /**
     * @var array<int, array{id: int, name: string, username: string, avatar_url: ?string}>
     */
    public array $mentionSuggestions = [];

    public bool $mentionSuggestionsOpen = false;

    public ?string $emojiTarget = null;

    public ?int $activeReplyCommentId = null;

    /**
     * @var array<int, bool>
     */
    public array $expandedReplyIds = [];

    public int $visibleTopLevelComments = CommentService::PREVIEW_TOP_LEVEL_LIMIT;

    public int $topLevelCommentCount = 0;

    public ?int $pinnedCommentId = null;

    public int $commentCount = 0;

    public string $threadFingerprint = '';

    public bool $hasFreshActivity = false;

    public int $freshActivityCount = 0;

    public ?string $statusMessage = null;

    public function mount(Post $post, bool $fullPage = false): void
    {
        $this->post = $post;
        $this->fullPage = $fullPage;
        $this->sort = in_array(session('post_comments_sort_'.$post->getKey(), 'oldest'), ['top', 'newest', 'oldest'], true)
            ? (string) session('post_comments_sort_'.$post->getKey(), 'oldest')
            : 'oldest';
        $this->restoreDraft();
        $this->syncThreadState();
    }

    /**
     * @return Collection<int, Comment>
     */
    public function comments(): Collection
    {
        $pinnedCommentId = $this->pinnedCommentId;
        $service = app(CommentService::class);

        $comments = $this->fullPage && trim($this->search) !== ''
            ? $service->searchWithinPost($this->post, $this->viewer(), $this->search)
            : ($this->fullPage
                ? $service->threadForPost($this->post, $this->viewer(), $this->sort)
                : $service->previewThread($this->post, $this->viewer(), $this->visibleTopLevelComments, CommentService::PREVIEW_REPLY_LIMIT, $this->expandedReplyIds));

        return $comments
            ->when($pinnedCommentId !== null, fn (Collection $comments): Collection => $comments
                ->reject(fn (Comment $comment): bool => (int) $comment->getKey() === (int) $pinnedCommentId)
                ->values());
    }

    public function pinnedComment(): ?Comment
    {
        if ($this->pinnedCommentId === null) {
            return null;
        }

        return app(CommentService::class)->inlineComment($this->post, $this->viewer(), $this->pinnedCommentId);
    }

    public function createComment(CreateCommentAction $action): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $body = $this->validatedInlineBody($this->body, 'body', $this->selectedGif);

        if ($body === null) {
            return;
        }

        try {
            $action->handle($viewer, $this->post, $body, null, $this->selectedGif);
        } catch (ValidationException $exception) {
            $this->copyValidationErrors($exception, 'body');

            return;
        }

        $this->body = '';
        $this->selectedGif = null;
        app(CommentDraftService::class)->discard($viewer, $this->post);
        $this->draftRestored = false;
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

        $this->activeReplyCommentId = null;
        $this->statusMessage = 'Reply posted.';
        $this->syncThreadState();
        $this->dispatchThreadUpdated();
    }

    public function autosaveDraft(CommentDraftService $drafts): void
    {
        $viewer = $this->viewer();

        if (! $viewer instanceof User) {
            return;
        }

        $drafts->save($viewer, $this->post, $this->body, $this->selectedGif);
    }

    public function discardDraft(CommentDraftService $drafts): void
    {
        $viewer = $this->viewer();

        if (! $viewer instanceof User) {
            return;
        }

        $drafts->discard($viewer, $this->post);
        $this->body = '';
        $this->selectedGif = null;
        $this->draftRestored = false;
        $this->statusMessage = 'Draft discarded.';
    }

    public function searchGifs(CommentGifService $gifs): void
    {
        $query = Str::of($this->gifSearch)->trim()->limit(60, '')->toString();
        $this->gifSearch = $query;
        $this->gifResults = mb_strlen($query) >= 2 ? $gifs->search($query) : [];
    }

    public function selectGif(int $index): void
    {
        $gif = $this->gifResults[$index] ?? null;

        if (! is_array($gif)) {
            return;
        }

        $this->selectedGif = [
            'gif_url' => (string) $gif['gif_url'],
            'gif_preview_url' => $gif['gif_preview_url'] ?? null,
            'gif_title' => $gif['title'] ?? null,
            'gif_provider' => $gif['gif_provider'] ?? null,
        ];
        $this->gifPickerOpen = false;
    }

    public function removeGif(): void
    {
        $this->selectedGif = null;
    }

    public function translateComment(int $commentId, CommentTranslationService $translations): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $comment = $this->threadComment($commentId);
        $translation = $translations->translate($comment, app()->getLocale());

        if ($translation === null) {
            $this->statusMessage = 'Translation is not available for this comment.';

            return;
        }

        $this->translations[$commentId] = (string) $translation->translated_body;
    }

    public function unsubscribeFromThread(int $commentId, CommentThreadSubscriptionService $subscriptions): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $subscriptions->unsubscribe($viewer, $this->threadComment($commentId));
        $this->statusMessage = 'Thread notifications muted.';
        $this->syncThreadState();
    }

    /**
     * @return array{success: true, data: array{comment_id: int, current_reaction: ?string, reactions_count: int, reaction_counts: array<string, int>}}
     */
    public function reactToComment(int $commentId, string $type, CommentService $comments): array
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $comment = $this->threadComment($commentId);

        abort_if($comment->trashed(), 404);
        Gate::forUser($viewer)->authorize('react', $comment);

        $type = Reaction::normalizeType($type);

        if (! in_array($type, Reaction::commentTypes(), true)) {
            throw ValidationException::withMessages([
                'reaction' => 'Choose a valid comment reaction.',
            ]);
        }

        $reaction = $comments->toggleReaction($comment, $viewer, $type);
        $comment->refresh();
        $this->syncThreadState();

        return [
            'success' => true,
            'data' => $this->commentReactionPayload($comment, $reaction),
        ];
    }

    public function startReply(int $commentId, string $username): void
    {
        $this->threadComment($commentId);
        $this->activeReplyCommentId = $commentId;

        if (blank($this->replyBodies[$commentId] ?? '')) {
            $this->replyBodies[$commentId] = '@'.Str::of($username)->lower()->replaceMatches('/[^a-z0-9-]/', '').toString().' ';
        }
    }

    public function loadMoreComments(): void
    {
        if ($this->fullPage) {
            return;
        }

        $this->visibleTopLevelComments += CommentService::PREVIEW_TOP_LEVEL_LIMIT;
        $this->hasFreshActivity = false;
        $this->freshActivityCount = 0;
    }

    public function setSort(string $sort): void
    {
        if (! in_array($sort, ['top', 'newest', 'oldest'], true)) {
            return;
        }

        $this->sort = $sort;
        session()->put('post_comments_sort_'.$this->post->getKey(), $sort);
    }

    public function toggleReplies(int $commentId): void
    {
        $this->threadComment($commentId);

        if ((bool) ($this->expandedReplyIds[$commentId] ?? false)) {
            unset($this->expandedReplyIds[$commentId]);

            return;
        }

        $this->expandedReplyIds[$commentId] = true;
    }

    public function startEditing(int $commentId): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $comment = $this->threadComment($commentId);

        Gate::forUser($viewer)->authorize('update', $comment);

        $this->editingCommentId = $commentId;
        $this->editBodies[$commentId] = (string) $comment->body;
        $this->resetErrorBag('editBodies.'.$commentId);
    }

    public function cancelEditing(): void
    {
        $this->editingCommentId = null;
    }

    public function updateComment(int $commentId, UpdateCommentAction $action): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $errorKey = 'editBodies.'.$commentId;
        $body = $this->validatedInlineBody((string) ($this->editBodies[$commentId] ?? ''), $errorKey);

        if ($body === null) {
            return;
        }

        try {
            $action->handle($viewer, $this->threadComment($commentId), $body);
        } catch (ValidationException $exception) {
            $this->copyValidationErrors($exception, $errorKey);

            return;
        }

        unset($this->editBodies[$commentId]);

        $this->editingCommentId = null;
        $this->statusMessage = 'Comment updated.';
        $this->syncThreadState();
        $this->dispatchThreadUpdated();
    }

    public function deleteComment(int $commentId, DeleteCommentAction $action): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $action->handle($viewer, $this->threadComment($commentId));

        $this->statusMessage = 'Comment removed.';
        $this->syncThreadState();
        $this->dispatchThreadUpdated();
    }

    public function openReport(int $commentId): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $comment = $this->threadComment($commentId);

        Gate::forUser($viewer)->authorize('report', $comment);

        $this->reportingCommentId = $commentId;
        $this->reportReason = Report::REASON_SPAM;
        $this->reportDetails = '';
        $this->resetErrorBag('report');
        $this->resetErrorBag('reportReason');
    }

    public function cancelReport(): void
    {
        $this->reportingCommentId = null;
        $this->reportReason = Report::REASON_SPAM;
        $this->reportDetails = '';
    }

    public function reportComment(CreateReportAction $action): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);
        abort_if($this->reportingCommentId === null, 404);

        $comment = $this->threadComment($this->reportingCommentId);

        Gate::forUser($viewer)->authorize('report', $comment);

        if (! in_array($this->reportReason, Report::REASONS, true)) {
            $this->addError('reportReason', 'Choose a valid report reason.');

            return;
        }

        try {
            $action->handle(
                $viewer,
                $comment,
                $this->reportReason,
                trim($this->reportDetails) !== '' ? trim($this->reportDetails) : null,
            );
        } catch (ValidationException $exception) {
            $this->copyValidationErrors($exception, 'report');

            return;
        }

        $reportedCommentId = $this->reportingCommentId;
        $this->cancelReport();
        $this->statusMessage = 'Comment reported. Thank you.';
        $this->dispatch('comments-thread-reported', postId: (int) $this->post->getKey(), commentId: $reportedCommentId);
    }

    public function blockCommenter(int $commentId, BlockService $blocks): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $comment = $this->threadComment($commentId);
        $comment->loadMissing('user');

        abort_if((int) $comment->user_id === (int) $viewer->getKey(), 403);

        $blocks->block($viewer, $comment->user);

        $this->statusMessage = '@'.$comment->user->username.' has been blocked.';
        $this->syncThreadState();
        $this->dispatchThreadUpdated();
    }

    public function pinComment(int $commentId): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);
        Gate::forUser($viewer)->authorize('pin', $this->post);

        $comment = $this->threadComment($commentId);

        abort_if($comment->trashed(), 404);

        $metadata = (array) ($this->post->metadata ?? []);
        $metadata['pinned_comment_id'] = (int) $comment->getKey();

        $this->post->forceFill(['metadata' => $metadata])->save();
        $this->pinnedCommentId = (int) $comment->getKey();
        $this->statusMessage = 'Comment pinned.';
    }

    public function unpinComment(): void
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);
        Gate::forUser($viewer)->authorize('pin', $this->post);

        $metadata = (array) ($this->post->metadata ?? []);
        unset($metadata['pinned_comment_id']);

        $this->post->forceFill(['metadata' => $metadata])->save();
        $this->pinnedCommentId = null;
        $this->statusMessage = 'Comment unpinned.';
    }

    public function searchMentionSuggestions(string $query, string $target): void
    {
        $normalized = Str::of($query)
            ->lower()
            ->replaceMatches('/[^a-z0-9-]/', '')
            ->limit(30, '')
            ->toString();

        $this->mentionTarget = $target;
        $this->mentionSearch = $normalized;

        if (mb_strlen($normalized) < 1) {
            $this->closeMentionSuggestions();

            return;
        }

        $viewerId = (int) ($this->viewer()?->getKey() ?? 0);

        $this->mentionSuggestions = User::query()
            ->where('username', 'like', $normalized.'%')
            ->when($viewerId > 0, fn ($query) => $query->where('id', '!=', $viewerId))
            ->orderBy('username')
            ->limit(5)
            ->get(['id', 'name', 'username', 'avatar_path'])
            ->map(fn (User $user): array => [
                'id' => (int) $user->getKey(),
                'name' => (string) $user->name,
                'username' => (string) $user->username,
                'avatar_url' => $user->avatar_url,
            ])
            ->values()
            ->all();
        $this->mentionSuggestionsOpen = $this->mentionSuggestions !== [];
    }

    public function closeMentionSuggestions(): void
    {
        $this->mentionSearch = '';
        $this->mentionSuggestions = [];
        $this->mentionSuggestionsOpen = false;
    }

    public function insertMention(string $username): void
    {
        $mention = '@'.Str::of($username)->lower()->replaceMatches('/[^a-z0-9-]/', '').toString().' ';
        $this->insertTextIntoTarget($mention, $this->mentionTarget);
        $this->closeMentionSuggestions();
    }

    public function insertEmoji(string $emoji, ?string $target = null): void
    {
        $allowed = ['🐾', '❤️', '🎉', '😂', '🥹', '🙏', '✨'];

        if (! in_array($emoji, $allowed, true)) {
            return;
        }

        $this->insertTextIntoTarget($emoji, $target ?? $this->emojiTarget ?? 'body');
    }

    public function refreshThread(): void
    {
        $previousFingerprint = $this->threadFingerprint;
        $previousCommentCount = $this->commentCount;

        $this->syncThreadState();

        if ($previousFingerprint !== '' && $previousFingerprint !== $this->threadFingerprint) {
            $this->hasFreshActivity = true;
            $this->freshActivityCount = max(0, $this->commentCount - $previousCommentCount);
            $this->dispatchThreadUpdated();
        }
    }

    private function viewer(): ?User
    {
        $viewer = auth()->user();

        return $viewer instanceof User ? $viewer : null;
    }

    private function restoreDraft(): void
    {
        $viewer = $this->viewer();

        if (! $viewer instanceof User) {
            return;
        }

        $draft = app(CommentDraftService::class)->restore($viewer, $this->post);

        if ($draft === null) {
            return;
        }

        $this->body = (string) $draft->body;
        $this->selectedGif = filled($draft->gif_url)
            ? [
                'gif_url' => (string) $draft->gif_url,
                'gif_preview_url' => $draft->gif_preview_url,
                'gif_title' => $draft->gif_title,
                'gif_provider' => $draft->gif_provider,
            ]
            : null;
        $this->draftRestored = true;
    }

    private function threadComment(int $commentId): Comment
    {
        return Comment::query()
            ->where('comments.post_id', $this->post->getKey())
            ->whereKey($commentId)
            ->firstOrFail();
    }

    /**
     * @param  array{gif_url?: string|null}|null  $gif
     */
    private function validatedInlineBody(string $body, string $errorKey, ?array $gif = null): ?string
    {
        $body = trim($body);

        $this->resetErrorBag($errorKey);

        if ($body === '' && blank($gif['gif_url'] ?? null)) {
            $this->addError($errorKey, 'Please write something before submitting.');

            return null;
        }

        if (mb_strlen($body) > CommentService::MAX_BODY_LENGTH) {
            $this->addError($errorKey, 'Comments may not be longer than 500 characters.');

            return null;
        }

        return $body;
    }

    private function insertTextIntoTarget(string $text, ?string $target): void
    {
        $target ??= 'body';

        if ($target === 'body') {
            $this->body = ltrim(rtrim($this->body).' '.$text);

            return;
        }

        if (str_starts_with($target, 'reply:')) {
            $commentId = (int) Str::after($target, 'reply:');
            $this->replyBodies[$commentId] = ltrim(rtrim((string) ($this->replyBodies[$commentId] ?? '')).' '.$text);

            return;
        }

        if (str_starts_with($target, 'edit:')) {
            $commentId = (int) Str::after($target, 'edit:');
            $this->editBodies[$commentId] = ltrim(rtrim((string) ($this->editBodies[$commentId] ?? '')).' '.$text);
        }
    }

    /**
     * @return array{comment_id: int, current_reaction: ?string, reactions_count: int, reaction_counts: array<string, int>}
     */
    private function commentReactionPayload(Comment $comment, ?Reaction $reaction): array
    {
        return [
            'comment_id' => (int) $comment->getKey(),
            'current_reaction' => $reaction instanceof Reaction ? Reaction::normalizeType((string) $reaction->type) : null,
            'reactions_count' => (int) $comment->reactions_count,
            'reaction_counts' => collect(Reaction::commentTypes())
                ->mapWithKeys(fn (string $reactionType): array => [
                    $reactionType => (int) $comment->getAttribute(Reaction::counterColumn($reactionType)),
                ])
                ->all(),
        ];
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
        $this->topLevelCommentCount = app(CommentService::class)->topLevelCount($this->post, $this->viewer());
        $freshPost = $this->post->fresh();
        $this->pinnedCommentId = (int) data_get($freshPost?->metadata ?? [], 'pinned_comment_id') ?: null;

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

<x-ui.card
    padding="base"
    wire:poll.visible.15s="refreshThread"
    x-data
    x-init="setInterval(() => { if ($wire.body || $wire.selectedGif) { $wire.autosaveDraft() } }, 10000)"
    data-ui="comments-thread"
>
    @php($comments = $this->comments())
    @php($pinnedComment = $this->pinnedComment())
    @php($remainingTopLevelComments = $fullPage ? 0 : max(0, $topLevelCommentCount - $visibleTopLevelComments))
    @php($topBodyLength = mb_strlen($body))

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-bold text-bark">Comments <span class="font-semibold text-fur">({{ number_format($commentCount) }})</span></p>
            <p class="mt-0.5 text-xs text-fur" aria-live="polite">
                @if ($hasFreshActivity)
                    {{ $freshActivityCount > 0 ? trans_choice(':count new comment|:count new comments', $freshActivityCount, ['count' => $freshActivityCount]) : 'Thread refreshed just now.' }}
                @elseif ($statusMessage)
                    {{ $statusMessage }}
                @else
                    {{ $fullPage ? 'Full comment thread for this post.' : 'Showing the latest conversation without leaving the feed.' }}
                @endif
            </p>
            @error('report')
                <p class="mt-1 text-xs font-semibold text-rose">{{ $message }}</p>
            @enderror
        </div>
        @if ($fullPage)
            <div class="flex flex-wrap items-center justify-end gap-2">
                @if($commentCount > 50)
                    <label class="relative block">
                        <span class="sr-only">Search comments</span>
                        <input type="search" wire:model.live.debounce.400ms="search" class="form-input min-h-9 w-48 rounded-[var(--radius-pill)] py-1.5 pl-8 pr-3 text-xs" placeholder="Search comments">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-fur">⌕</span>
                    </label>
                @endif
                <div class="inline-flex rounded-[var(--radius-pill)] border border-whisker/35 bg-cream/60 p-1 text-xs font-semibold text-fur" aria-label="Sort comments">
                    @foreach(['top' => 'Top', 'newest' => 'Newest', 'oldest' => 'Oldest'] as $sortValue => $sortLabel)
                        <button type="button" wire:click="setSort('{{ $sortValue }}')" class="rounded-[var(--radius-pill)] px-3 py-1 transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw {{ $sort === $sortValue ? 'bg-warm-white text-bark shadow-sm' : 'hover:bg-warm-white/70' }}" aria-pressed="{{ $sort === $sortValue ? 'true' : 'false' }}">
                            {{ $sortLabel }}
                        </button>
                    @endforeach
                </div>
            </div>
        @else
            <a href="{{ route('posts.show', $post) }}#comments" class="text-xs font-semibold text-paw hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                View all {{ number_format($commentCount) }} comments
            </a>
        @endif
    </div>

    @if($pinnedComment)
        <div class="mb-4 rounded-[var(--radius-soft)] border border-amber/30 bg-amber-light/40 p-3" data-ui="comments-pinned-comment">
            <div class="mb-2 flex items-center justify-between gap-3 text-xs font-bold uppercase tracking-wide text-amber">
                <span>📌 Pinned</span>
                @can('pin', $post)
                    <button type="button" wire:click="unpinComment" class="text-[0.7rem] text-amber hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Unpin</button>
                @endcan
            </div>
            <x-comment-item
                :comment="$pinnedComment"
                :post="$post"
                :livewire="true"
                :editing-comment-id="$editingCommentId"
                :mention-target="$mentionTarget"
                :mention-suggestions="$mentionSuggestions"
                :mention-suggestions-open="$mentionSuggestionsOpen"
                :expanded-reply-ids="$expandedReplyIds"
                :translations="$translations"
                :search-query="$search"
            />
        </div>
    @endif

    @auth
        @can('create', [Comment::class, $post])
            <form
                wire:submit.prevent="createComment"
                class="mb-5 flex items-start gap-3"
                data-ui="comments-thread-form"
                x-data="{
                    emojiOpen: false,
                    detectMention(value, target) {
                        const match = value.match(/(?:^|\s)@([A-Za-z0-9-]{1,30})$/)

                        if (match) {
                            $wire.searchMentionSuggestions(match[1], target)
                            return
                        }

                        $wire.closeMentionSuggestions()
                    },
                }"
            >
                <x-ui.avatar :src="auth()->user()?->avatar_url" :name="auth()->user()?->name" :user="auth()->user()" size="sm" class="mt-1"/>
                <div class="min-w-0 flex-1">
                    <div class="relative">
                        <textarea
                            wire:model.live.debounce.300ms="body"
                            rows="1"
                            maxlength="{{ CommentService::MAX_BODY_LENGTH }}"
                            class="form-textarea min-h-11 w-full resize-none overflow-hidden py-2.5 pr-24 text-sm"
                            placeholder="Write a comment..."
                            oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"
                            x-on:input.debounce.250ms="detectMention($event.target.value, 'body')"
                            x-on:keydown.enter.exact.prevent="$wire.createComment()"
                            x-on:keydown.escape="emojiOpen = false; $wire.closeMentionSuggestions()"
                        ></textarea>
                        <div class="absolute bottom-2 right-2 flex items-center gap-1">
                        <button type="button" class="inline-flex min-h-8 min-w-8 items-center justify-center rounded-[var(--radius-pill)] text-xs font-bold text-fur transition hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" wire:click="$toggle('gifPickerOpen')" aria-label="Add GIF">
                            GIF
                        </button>
                        <button type="button" class="inline-flex min-h-8 min-w-8 items-center justify-center rounded-[var(--radius-pill)] text-sm text-fur transition hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" x-on:click="emojiOpen = ! emojiOpen" aria-label="Add emoji">
                            😊
                        </button>
                        </div>
                        <div
                            x-show="emojiOpen"
                            x-cloak
                            x-transition.opacity.duration.150ms
                            class="absolute right-2 top-full z-30 mt-2 flex gap-1 rounded-[var(--radius-soft)] border border-whisker/35 bg-warm-white p-2 shadow-card"
                        >
                            @foreach(['🐾', '❤️', '🎉', '😂', '🥹', '🙏', '✨'] as $emoji)
                                <button type="button" wire:click="insertEmoji('{{ $emoji }}', 'body')" x-on:click="emojiOpen = false" class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-[var(--radius-pill)] text-lg transition hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">{{ $emoji }}</button>
                            @endforeach
                        </div>
                        @if ($gifPickerOpen)
                            <div class="absolute left-0 right-0 top-full z-40 mt-2 rounded-[var(--radius-soft)] border border-whisker/35 bg-warm-white p-3 shadow-card" data-ui="comment-gif-picker">
                                <div class="flex items-center gap-2">
                                    <input type="search" wire:model.live.debounce.350ms="gifSearch" wire:keydown.debounce.350ms="searchGifs" class="form-input min-h-9 flex-1 text-sm" placeholder="Search GIFs">
                                    <button type="button" wire:click="searchGifs" class="rounded-[var(--radius-pill)] px-3 py-1.5 text-xs font-bold text-paw hover:bg-paw-light/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Search</button>
                                </div>
                                @if($gifResults !== [])
                                    <div class="mt-3 grid max-h-56 grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3">
                                        @foreach($gifResults as $index => $gif)
                                            <button type="button" wire:click="selectGif({{ $index }})" class="overflow-hidden rounded-[var(--radius-soft)] border border-whisker/35 bg-cream transition hover:border-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                                                <img src="{{ $gif['gif_preview_url'] ?: $gif['gif_url'] }}" alt="{{ $gif['title'] }}" class="h-24 w-full object-cover" loading="lazy">
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif(mb_strlen($gifSearch) >= 2)
                                    <p class="mt-3 text-xs text-fur">No GIFs found.</p>
                                @endif
                            </div>
                        @endif
                        @if ($mentionSuggestionsOpen && $mentionTarget === 'body' && $mentionSuggestions !== [])
                            <div class="absolute left-0 right-0 top-full z-40 mt-2 overflow-hidden rounded-[var(--radius-soft)] border border-whisker/35 bg-warm-white shadow-card" role="listbox" aria-label="Mention suggestions">
                                @foreach ($mentionSuggestions as $suggestion)
                                    <button type="button" wire:click="insertMention('{{ $suggestion['username'] }}')" class="flex w-full items-center gap-3 px-3 py-2 text-left transition hover:bg-cream focus:bg-cream focus:outline-none" role="option">
                                        <x-ui.avatar :src="$suggestion['avatar_url']" :name="$suggestion['name']" size="sm"/>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-semibold text-bark">{{ $suggestion['name'] }}</span>
                                            <span class="block truncate text-xs text-fur">&#64;{{ $suggestion['username'] }}</span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @error('body')
                        <p class="mt-1 text-xs font-semibold text-rose">{{ $message }}</p>
                    @enderror
                    @if($draftRestored)
                        <div class="mt-2 inline-flex items-center gap-2 rounded-[var(--radius-pill)] bg-paw-light/45 px-3 py-1 text-xs font-semibold text-paw">
                            <span>Draft restored</span>
                            <button type="button" wire:click="discardDraft" class="text-fur hover:text-bark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Discard</button>
                        </div>
                    @endif
                    @if($selectedGif)
                        <div class="mt-3 max-w-xs overflow-hidden rounded-[var(--radius-soft)] border border-whisker/35 bg-cream/50">
                            <div class="relative">
                                <img src="{{ $selectedGif['gif_preview_url'] ?: $selectedGif['gif_url'] }}" alt="{{ $selectedGif['gif_title'] ?: 'Selected GIF' }}" class="max-h-48 w-full object-cover" loading="lazy">
                                <button type="button" wire:click="removeGif" class="absolute right-2 top-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-bark/70 text-xs font-bold text-warm-white transition hover:bg-bark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" aria-label="Remove GIF">✕</button>
                            </div>
                            @if($selectedGif['gif_title'])
                                <p class="truncate px-3 py-2 text-xs text-fur">{{ $selectedGif['gif_title'] }}</p>
                            @endif
                        </div>
                    @endif
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs text-fur" aria-live="polite">
                            <span wire:loading.remove wire:target="createComment">{{ $statusMessage }}</span>
                            <span wire:loading wire:target="createComment">Posting comment...</span>
                        </p>
                        @if($topBodyLength > 400)
                            <p class="text-xs font-semibold {{ $topBodyLength > CommentService::MAX_BODY_LENGTH ? 'text-rose' : 'text-amber' }}" aria-live="polite">
                                {{ CommentService::MAX_BODY_LENGTH - $topBodyLength }} characters left
                            </p>
                        @endif
                        <x-ui.button type="submit" size="sm" variant="primary" wire:loading.attr="disabled" wire:target="createComment">
                            Post Comment
                        </x-ui.button>
                    </div>
                </div>
            </form>
        @endcan
    @else
        <div class="mb-5 flex items-center gap-3 rounded-[var(--radius-soft)] border border-whisker/40 bg-cream/50 p-3 text-sm text-fur">
            <div class="h-9 w-9 rounded-full bg-whisker/40" aria-hidden="true"></div>
            <div>
            <a href="{{ route('login') }}" class="font-semibold text-paw hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Log in</a>
            to join the conversation.
            </div>
        </div>
    @endauth

    @forelse ($comments as $comment)
        @if ($loop->first)
            <div class="space-y-4">
        @endif
            <x-comment-item
                :comment="$comment"
                :post="$post"
                :livewire="true"
                :editing-comment-id="$editingCommentId"
                :mention-target="$mentionTarget"
                :mention-suggestions="$mentionSuggestions"
                :mention-suggestions-open="$mentionSuggestionsOpen"
                :expanded-reply-ids="$expandedReplyIds"
                :translations="$translations"
                :search-query="$search"
            />
        @if ($loop->last)
            </div>
        @endif
    @empty
        <p class="text-sm text-fur">No comments yet.</p>
    @endforelse

    @if($remainingTopLevelComments > 0)
        <div class="mt-4">
            <x-ui.button type="button" variant="secondary" size="sm" wire:click="loadMoreComments" wire:loading.attr="disabled" wire:target="loadMoreComments" class="w-full justify-center">
                Load more comments
            </x-ui.button>
        </div>
    @endif

    @if($reportingCommentId !== null)
        <div class="fixed inset-0 z-[90] flex items-end justify-center bg-bark/35 px-4 py-6 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="comment-report-title" data-ui="comment-report-modal">
            <div class="w-full max-w-md rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white p-5 shadow-card-hover">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 id="comment-report-title" class="text-base font-bold text-bark">Report comment</h3>
                        <p class="mt-1 text-sm text-fur">Choose the reason that best matches what is wrong.</p>
                    </div>
                    <button type="button" wire:click="cancelReport" class="rounded-[var(--radius-pill)] p-2 text-fur hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" aria-label="Close report dialog">✕</button>
                </div>
                <div class="mt-4 space-y-2">
                    @foreach([
                        Report::REASON_SPAM => 'Spam',
                        Report::REASON_HARASSMENT => 'Harassment',
                        Report::REASON_MISINFORMATION => 'Misinformation',
                        Report::REASON_OTHER => 'Inappropriate or off-topic',
                    ] as $reason => $label)
                        <label class="flex cursor-pointer items-center gap-3 rounded-[var(--radius-soft)] border border-whisker/35 bg-cream/40 px-3 py-2 text-sm font-semibold text-bark transition hover:bg-cream">
                            <input type="radio" wire:model.live="reportReason" value="{{ $reason }}" class="text-paw focus:ring-paw">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                    @error('reportReason')
                        <p class="text-xs font-semibold text-rose">{{ $message }}</p>
                    @enderror
                </div>
                <textarea wire:model.live.debounce.300ms="reportDetails" rows="2" maxlength="500" class="form-textarea mt-4 w-full text-sm" placeholder="Optional context for moderators"></textarea>
                <div class="mt-4 flex justify-end gap-2">
                    <x-ui.button type="button" variant="ghost" size="sm" wire:click="cancelReport">Cancel</x-ui.button>
                    <x-ui.button type="button" variant="primary" size="sm" wire:click="reportComment" wire:loading.attr="disabled" wire:target="reportComment">Submit report</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</x-ui.card>
