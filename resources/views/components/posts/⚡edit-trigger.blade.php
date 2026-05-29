<?php

use App\Models\Content\Post;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    use AuthorizesRequests;

    public int $postId;

    public bool $open = false;

    public function mount(Post $post): void
    {
        $this->postId = (int) $post->getKey();
    }

    public function open(): void
    {
        $post = Post::query()->whereKey($this->postId)->firstOrFail();

        $this->authorize('update', $post);

        $this->open = true;
    }

    #[On('post-edit-closed')]
    public function closeFromComposer(?int $postId = null): void
    {
        if ($postId !== null && (int) $postId !== $this->postId) {
            return;
        }

        $this->open = false;
    }

    #[On('post-updated')]
    public function closeAfterUpdate(?int $postId = null): void
    {
        $this->closeFromComposer($postId);
    }
};
?>

<div>
 <x-ui.dropdown-item type="button" wire:click="open" data-ui="post-card-menu-edit">
 Edit post
 </x-ui.dropdown-item>

 @if ($open)
 <livewire:posts.composer
 mode="modal"
 :edit-post-id="$postId"
 :key="'post-edit-composer-'.$postId.'-'.$this->getId()"
 />
 @endif
</div>
