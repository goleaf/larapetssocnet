<?php

use App\Models\Content\Post;
use App\Services\PostDeletionCascadeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public int $postId;

    public bool $open = false;

    public string $previewText = '';

    public ?string $previewMediaUrl = null;

    public bool $previewMediaIsVideo = false;

    public function mount(Post $post): void
    {
        $this->postId = (int) $post->getKey();
    }

    public function open(): void
    {
        $post = $this->findPostForDeletion();

        $this->authorize('delete', $post);

        $this->previewText = Str::limit(trim((string) $post->body), 150);

        $media = $post->mediaItemsForDisplay()->first();
        $this->previewMediaUrl = $media ? Post::mediaItemUrl($media) : null;
        $this->previewMediaIsVideo = $media ? Post::mediaItemIsVideo($media) : false;
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function confirm(PostDeletionCascadeService $cascade): void
    {
        $post = $this->findPostForDeletion();

        $this->authorize('delete', $post);

        $this->open = false;

        DB::transaction(function () use ($post): void {
            if (! $post->trashed()) {
                $post->delete();
            }
        });

        $cascade->cascade($this->postId, (int) auth()->id());

        $this->dispatch('post-delete-requested', postId: $this->postId);
    }

    private function findPostForDeletion(): Post
    {
        return Post::query()
            ->with(['postMedia'])
            ->withTrashed()
            ->whereKey($this->postId)
            ->firstOrFail();
    }
};
?>

<div>
 <x-ui.dropdown-item type="button" variant="danger" wire:click="open" data-ui="post-card-menu-delete">
 Delete post
 </x-ui.dropdown-item>

 @if ($open)
 <div
 class="fixed inset-0 z-[70] overflow-y-auto px-4 py-6 sm:px-6"
 role="dialog"
 aria-modal="true"
 aria-labelledby="post-delete-title-{{ $this->getId() }}"
 wire:keydown.escape="close"
 >
 <div class="fixed inset-0 bg-bark/35 backdrop-blur-sm" aria-hidden="true" wire:click="close"></div>

 <div class="relative mx-auto flex min-h-full max-w-lg items-center justify-center">
 <div class="w-full overflow-hidden rounded-[var(--radius-panel)] border ui-border bg-white shadow-xl">
 <div class="border-b ui-border px-6 py-5">
 <p id="post-delete-title-{{ $this->getId() }}" class="text-lg font-semibold ui-text">Delete post?</p>
 <p class="mt-2 text-sm leading-6 shell-text-muted">
 This action cannot be undone. The post will be permanently removed from your profile, your followers' feeds, and all other places it appears.
 </p>
 </div>

 <div class="space-y-4 px-6 py-5">
 <div class="rounded-[var(--radius-soft)] border ui-border bg-cream/60 p-4">
 @if ($previewMediaUrl)
 @if ($previewMediaIsVideo)
 <video
 src="{{ $previewMediaUrl }}"
 class="mb-3 aspect-video w-full rounded-[var(--radius-soft)] bg-bark/10 object-cover"
 muted
 playsinline
 preload="metadata"
 ></video>
 @else
 <img
 src="{{ $previewMediaUrl }}"
 alt=""
 class="mb-3 aspect-video w-full rounded-[var(--radius-soft)] object-cover"
 >
 @endif
 @endif

 <p class="text-sm leading-6 ui-text">
 {{ $previewText !== '' ? $previewText : 'This post has no text content.' }}
 </p>
 </div>

 <p class="rounded-[var(--radius-soft)] border border-rose/30 bg-rose-light px-4 py-3 text-sm font-medium text-rose">
 Delete this post? This action cannot be undone. The post will be permanently removed from your profile, your followers' feeds, and all other places it appears.
 </p>
 </div>

 <div class="flex flex-col-reverse gap-3 border-t ui-border bg-cream/40 px-6 py-4 sm:flex-row sm:justify-end">
 <x-ui.button type="button" variant="secondary" wire:click="close">
 Cancel
 </x-ui.button>
 <x-ui.button type="button" variant="danger" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm">
 <span wire:loading.remove wire:target="confirm">Delete post</span>
 <span wire:loading wire:target="confirm">Deleting...</span>
 </x-ui.button>
 </div>
 </div>
 </div>
 </div>
 @endif
</div>
