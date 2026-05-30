<?php

namespace App\Livewire\Comments;

use App\Actions\Comments\CreateCommentAction;
use App\Actions\Comments\CreateCommentData;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\CommentDraftService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TopLevelCommentComposer extends Component
{
    #[Locked]
    public int $postId;

    public string $content = '';

    public ?string $gifUrl = null;

    public bool $isSubmitting = false;

    public ?int $draftId = null;

    /**
     * @var list<array{id: int, name: string, username: string, avatar_url: ?string}>
     */
    public array $mentionSuggestions = [];

    public function mount(int $postId, CommentDraftService $drafts): void
    {
        $this->postId = $postId;
        $viewer = Auth::user();

        if (! $viewer instanceof User) {
            return;
        }

        $post = Post::query()->whereKey($postId)->first();

        if (! $post instanceof Post) {
            return;
        }

        $draft = $drafts->restore($viewer, $post);

        if ($draft === null) {
            return;
        }

        $this->draftId = (int) $draft->getKey();
        $this->dispatch('draft-found', savedAt: $draft->last_autosaved_at?->diffForHumans() ?? 'recently');
    }

    public function updatedContent(): void
    {
        $partial = $this->activeMentionPartial($this->content);

        if ($partial === null) {
            $this->mentionSuggestions = [];

            return;
        }

        $this->loadMentionSuggestions($partial);
    }

    public function loadMentionSuggestions(string $partialUsername): void
    {
        $partialUsername = strtolower(trim($partialUsername));

        if ($partialUsername === '') {
            $this->mentionSuggestions = [];

            return;
        }

        $this->mentionSuggestions = User::query()
            ->where('username', 'like', addcslashes($partialUsername, '%_\\').'%')
            ->orderBy('username')
            ->limit(5)
            ->get(['id', 'name', 'username', 'avatar_path', 'profile_photo_path'])
            ->map(fn (User $user): array => [
                'id' => (int) $user->getKey(),
                'name' => (string) $user->name,
                'username' => (string) $user->username,
                'avatar_url' => is_string($user->avatar_url) ? $user->avatar_url : null,
            ])
            ->values()
            ->all();
    }

    public function selectMention(int $userId, string $username): void
    {
        $this->dispatch('mention-selected', userId: $userId, username: $username);
        $this->mentionSuggestions = [];
    }

    public function autosaveDraft(CommentDraftService $drafts): void
    {
        $viewer = Auth::user();
        $post = Post::query()->whereKey($this->postId)->first();

        if (! $viewer instanceof User || ! $post instanceof Post) {
            return;
        }

        $draft = $drafts->save($viewer, $post, $this->content, $this->gifPayload());
        $this->draftId = $draft?->getKey();
    }

    public function discardDraft(CommentDraftService $drafts): void
    {
        $viewer = Auth::user();
        $post = Post::query()->whereKey($this->postId)->first();

        if ($viewer instanceof User && $post instanceof Post) {
            $drafts->discard($viewer, $post);
        }

        $this->draftId = null;
    }

    public function submit(CreateCommentAction $action, CommentDraftService $drafts): void
    {
        $viewer = Auth::user();
        $post = Post::query()->whereKey($this->postId)->firstOrFail();

        if (! $viewer instanceof User) {
            return;
        }

        $validated = $this->validate([
            'content' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        $this->isSubmitting = true;
        $comment = $action->handle($viewer, new CreateCommentData(
            post: $post,
            body: (string) $validated['content'],
            parentId: null,
            gif: $this->gifPayload(),
        ));

        $this->content = '';
        $this->gifUrl = null;
        $this->mentionSuggestions = [];
        $this->isSubmitting = false;
        $drafts->discard($viewer, $post);
        $this->draftId = null;

        $this->dispatch('comment-created', postId: $this->postId, commentId: (int) $comment->getKey());
    }

    public function openGifPicker(): void
    {
        $this->dispatch('open-comment-gif-picker');
    }

    public function clearGifUrl(): void
    {
        $this->gifUrl = null;
    }

    public function render(): View
    {
        return view('livewire.comments.top-level-comment-composer');
    }

    private function activeMentionPartial(string $content): ?string
    {
        if (preg_match('/(?:^|\s)@([A-Za-z0-9_-]{1,30})$/', $content, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }

    /**
     * @return array{gif_url?: string|null, gif_provider?: string|null}|null
     */
    private function gifPayload(): ?array
    {
        if (! filled($this->gifUrl)) {
            return null;
        }

        return [
            'gif_url' => $this->gifUrl,
            'gif_provider' => 'tenor',
        ];
    }
}
