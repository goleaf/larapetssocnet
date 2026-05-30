<?php

namespace App\Livewire\Comments;

use App\Actions\Comments\CreateCommentAction;
use App\Actions\Comments\CreateCommentData;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReplyComposer extends Component
{
    #[Locked]
    public int $postId;

    #[Locked]
    public int $parentCommentId;

    public string $content = '';

    /**
     * @var list<array{id: int, name: string, username: string, avatar_url: ?string}>
     */
    public array $mentionSuggestions = [];

    public bool $isSubmitting = false;

    public function mount(int $postId, int $parentCommentId): void
    {
        $this->postId = $postId;
        $this->parentCommentId = $parentCommentId;

        $username = Comment::query()
            ->join('users', 'users.id', '=', 'comments.user_id')
            ->where('comments.id', $parentCommentId)
            ->value('users.username');

        $this->content = is_string($username) && $username !== '' ? '@'.$username.' ' : '';
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

    public function submit(CreateCommentAction $action): void
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
            parentId: $this->parentCommentId,
        ));

        $this->isSubmitting = false;
        $this->content = '';
        $this->mentionSuggestions = [];
        $this->dispatch('reply-created', parentCommentId: $this->parentCommentId, commentId: (int) $comment->getKey());
    }

    public function render(): View
    {
        return view('livewire.comments.reply-composer');
    }

    private function activeMentionPartial(string $content): ?string
    {
        if (preg_match('/(?:^|\s)@([A-Za-z0-9_-]{1,30})$/', $content, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }
}
