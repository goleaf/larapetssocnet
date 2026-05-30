<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Post $post;

    public ?int $viewerId = null;

    public string $context = 'feed';

    public ?string $instance = null;

    public bool $commentsOpen = false;

    public function mount(Post $post, ?int $viewerId = null, string $context = 'feed', ?string $instance = null): void
    {
        $this->post = $post;
        $this->viewerId = $viewerId;
        $this->context = $context;
        $this->instance = $instance;
    }

    public function toggleComments(): void
    {
        $this->commentsOpen = ! $this->commentsOpen;
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
    }

    public function viewer(): ?User
    {
        $viewer = auth()->user();

        if ($viewer instanceof User) {
            return $viewer;
        }

        return $this->viewerId !== null ? User::query()->find($this->viewerId) : null;
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
    />

    @if ($commentsOpen)
        <section class="mt-3" data-ui="feed-post-livewire-comments">
            <livewire:posts.comments-thread :post="$post" :key="'feed-post-comments-'.$post->getKey()" />
        </section>
    @endif
</div>
