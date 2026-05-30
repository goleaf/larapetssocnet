<?php

use App\Actions\Engagement\SetReactionAction;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Post $post;

    public ?int $viewerId = null;

    public string $context = 'feed';

    public ?string $instance = null;

    public bool $commentsOpen = false;

    public ?string $currentReaction = null;

    public bool $viewerHasReacted = false;

    public int $displayedReactionCount = 0;

    /**
     * @var array<string, int>
     */
    public array $displayedReactionCounts = [];

    public function mount(Post $post, ?int $viewerId = null, string $context = 'feed', ?string $instance = null): void
    {
        $this->post = $post;
        $this->viewerId = $viewerId;
        $this->context = $context;
        $this->instance = $instance;
        $this->syncReactionStateFromPost($post, $this->viewer());
    }

    public function toggleComments(): void
    {
        $this->commentsOpen = ! $this->commentsOpen;
    }

    /**
     * @return array{success: true, action: 'added'|'changed'|'removed', data: array{post_id: int, likes_count: int, reactions_count: int, reaction_counts: array<string, int>, current_reaction: ?string}}
     */
    public function react(string $type, SetReactionAction $setReaction): array
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);
        Gate::forUser($viewer)->authorize('react', $this->post);

        $previousReaction = $this->currentReaction;
        $previousCount = $this->displayedReactionCount;
        $previousCounts = $this->displayedReactionCounts;
        $normalizedType = Reaction::normalizeType($type);
        $nextReaction = $previousReaction === $normalizedType ? null : $normalizedType;

        $this->syncOptimisticReactionState($previousReaction, $nextReaction);

        try {
            $result = $setReaction->handle($viewer, $this->post, $type);
        } catch (\Throwable $exception) {
            $this->currentReaction = $previousReaction;
            $this->viewerHasReacted = filled($previousReaction);
            $this->displayedReactionCount = $previousCount;
            $this->displayedReactionCounts = $previousCounts;

            throw $exception;
        }

        $this->syncReactionStateFromResult($result);

        return [
            'success' => true,
            'action' => $result['action'],
            'data' => [
                'post_id' => (int) $this->post->getKey(),
                'likes_count' => $result['likes_count'],
                'reactions_count' => $result['reactions_count'],
                'reaction_counts' => $result['reaction_counts'],
                'current_reaction' => $result['current_reaction'],
            ],
        ];
    }

    #[On('post-card-refresh')]
    public function refreshCard(?int $postId = null): void
    {
        if ($postId !== null && $postId !== (int) $this->post->getKey()) {
            return;
        }

        $this->post = Post::query()
            ->withFeedRelations($this->viewer())
            ->whereKey($this->post->getKey())
            ->firstOrFail();

        $this->syncReactionStateFromPost($this->post, $this->viewer());
    }

    public function viewer(): ?User
    {
        $viewer = auth()->user();

        if ($viewer instanceof User) {
            return $viewer;
        }

        return $this->viewerId !== null ? User::query()->find($this->viewerId) : null;
    }

    private function syncReactionStateFromPost(Post $post, ?User $viewer): void
    {
        $rawViewerReaction = $post->getAttribute('current_user_reaction_type');

        if ($rawViewerReaction === null && $post->relationLoaded('reactions') && $viewer instanceof User) {
            $rawViewerReaction = $post->reactions->firstWhere('user_id', $viewer->getKey())?->type;
        }

        $this->currentReaction = filled($rawViewerReaction) ? Reaction::normalizeType((string) $rawViewerReaction) : null;
        $this->viewerHasReacted = filled($this->currentReaction) || (bool) ($post->liked_by_viewer ?? false);

        if ($this->currentReaction === null && $this->viewerHasReacted) {
            $this->currentReaction = Reaction::defaultType();
        }

        $this->displayedReactionCount = (int) ($post->reactions_count ?? $post->likes_count ?? 0);
        $this->displayedReactionCounts = Reaction::countMapForModel($post);
    }

    private function syncOptimisticReactionState(?string $previousReaction, ?string $nextReaction): void
    {
        $this->currentReaction = $nextReaction;
        $this->viewerHasReacted = filled($nextReaction);
        $this->displayedReactionCount = max(0, $this->displayedReactionCount + match (true) {
            blank($previousReaction) && filled($nextReaction) => 1,
            filled($previousReaction) && blank($nextReaction) => -1,
            default => 0,
        });

        $counts = $this->displayedReactionCounts;

        if (filled($previousReaction)) {
            $counts[$previousReaction] = max(0, (int) ($counts[$previousReaction] ?? 0) - 1);
        }

        if (filled($nextReaction)) {
            $counts[$nextReaction] = (int) ($counts[$nextReaction] ?? 0) + 1;
        }

        $this->displayedReactionCounts = $counts;
    }

    /**
     * @param  array{action: 'added'|'changed'|'removed', current_reaction: ?string, likes_count: int, reactions_count: int, reaction_counts: array<string, int>}  $result
     */
    private function syncReactionStateFromResult(array $result): void
    {
        $this->currentReaction = $result['current_reaction'];
        $this->viewerHasReacted = filled($result['current_reaction']);
        $this->displayedReactionCount = $result['reactions_count'];
        $this->displayedReactionCounts = $result['reaction_counts'];

        $this->post->forceFill([
            'likes_count' => $result['likes_count'],
            'reactions_count' => $result['reactions_count'],
            ...collect($result['reaction_counts'])
                ->mapWithKeys(fn (int $count, string $type): array => [Reaction::counterColumn($type) => $count])
                ->all(),
        ]);
    }
};
?>

<div data-ui="feed-post-livewire-card">
    <x-post-card
        :post="$post"
        :viewer="$this->viewer()"
        :context="$context"
        :instance="$instance ?? 'feed-'.$post->getKey()"
        :livewire-comments="true"
        :livewire-reactions="true"
    />

    @if ($commentsOpen)
        <section class="mt-3" wire:transition.opacity.duration.200ms data-ui="feed-post-livewire-comments">
            <livewire:comments.comment-section :post-id="$post->getKey()" :key="'feed-post-comments-'.$post->getKey()" />
        </section>
    @endif
</div>
