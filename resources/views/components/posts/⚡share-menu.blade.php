<?php

use App\Actions\Engagement\TrackShareAction;
use App\Actions\Posts\CreateRepostAction;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    use AuthorizesRequests;

    public int $postId;

    public int $sharesCount;

    public string $postUrl;

    public string $authorName;

    public bool $quoteComposerOpen = false;

    public int $quoteComposerInstance = 0;

    public function mount(Post $post, string $authorName = 'a community member'): void
    {
        $this->postId = (int) $post->getKey();
        $this->sharesCount = (int) ($post->shares_count ?? 0);
        $this->postUrl = route('posts.show', $post->uuid ?: $post->getKey());
        $this->authorName = $authorName;
    }

    public function repost(CreateRepostAction $reposts): void
    {
        $post = $this->postForSharing();

        $result = $reposts->handle($this->viewer(), $post);

        $this->sharesCount = $result['shares_count'];

        $this->dispatch(
            'post-reposted',
            postId: $this->postId,
            repostId: (int) $result['post']->getKey(),
            sharesCount: $this->sharesCount,
        );
        $this->dispatch('toast-message', message: 'Reposted!', type: 'success');
    }

    public function trackCopyLink(TrackShareAction $shares): void
    {
        $viewer = auth()->user();

        if (! $viewer instanceof User) {
            return;
        }

        $post = $this->postForSharing();
        $result = $shares->handle($viewer, $post, 'copy_link');

        $this->sharesCount = $result['shares_count'];
    }

    public function openQuoteComposer(): void
    {
        $this->authorize('share', $this->postForSharing());

        $this->quoteComposerOpen = true;
        $this->quoteComposerInstance++;
    }

    #[On('post-composer-closed')]
    #[On('post-created')]
    public function closeQuoteComposer(): void
    {
        $this->quoteComposerOpen = false;
    }

    private function postForSharing(): Post
    {
        return Post::query()
            ->whereKey($this->postId)
            ->firstOrFail();
    }

    private function viewer(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
};
?>

<div
 class="relative flex sm:inline-flex"
 x-data="postShareMenu({ url: @js($postUrl) })"
 x-on:keydown.escape.window="close()"
>
 <x-ui.button
 type="button"
 size="sm"
 variant="ghost"
 class="min-h-11 w-full sm:w-auto"
 aria-label="{{ __('Share post by :name', ['name' => $authorName]) }}"
 aria-haspopup="menu"
 x-ref="trigger"
 x-bind:aria-expanded="open"
 x-on:click="toggle()"
 x-bind:disabled="copyBusy"
 >
 <span>Share</span>
 <span class="opacity-80" aria-live="polite">{{ $sharesCount }}</span>
 </x-ui.button>

 <template x-teleport="body">
 <div
 x-cloak
 x-show="open"
 x-transition.opacity.duration.150ms
 class="fixed inset-0 z-[65] sm:pointer-events-none"
 >
 <button type="button" class="absolute inset-0 bg-bark/35 sm:hidden" aria-label="Close share menu" x-on:click="close()"></button>

 <div
 class="absolute inset-x-0 bottom-0 overflow-hidden rounded-t-[var(--radius-card)] border ui-border bg-white shadow-xl sm:inset-auto sm:pointer-events-auto sm:rounded-[var(--radius-card)]"
 x-bind:style="menuStyle"
 role="menu"
 aria-label="Share options"
 x-on:click.outside="close()"
 >
 <div class="border-b ui-border px-4 py-3 sm:hidden">
 <p class="text-sm font-semibold ui-text">Share post</p>
 </div>

 <button
 type="button"
 class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-semibold ui-text transition hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-60"
 role="menuitem"
 wire:click="repost"
 wire:loading.attr="disabled"
 wire:target="repost"
 x-on:click="close()"
 >
 <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-paw/10 text-paw" aria-hidden="true">↻</span>
 <span wire:loading.remove wire:target="repost">Repost</span>
 <span wire:loading wire:target="repost">Reposting...</span>
 </button>

 <button
 type="button"
 class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-semibold ui-text transition hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 role="menuitem"
 wire:click="openQuoteComposer"
 x-on:click="close()"
 >
 <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-leaf/10 text-leaf" aria-hidden="true">”</span>
 <span>Quote post</span>
 </button>

 <div class="relative">
 <button
 type="button"
 class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-semibold ui-text transition hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-60"
 role="menuitem"
 x-ref="copyButton"
 x-bind:disabled="copyBusy"
 x-on:click="copyLink($wire)"
 >
 <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-fur/10 text-fur" aria-hidden="true">⧉</span>
 <span>Copy link</span>
 </button>

 <span
 x-cloak
 x-show="copied"
 x-transition.opacity.duration.150ms
 class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-bark px-3 py-1 text-xs font-semibold text-white shadow-sm"
 role="status"
 >
 Link copied!
 </span>
 </div>
 </div>
 </div>
 </template>

 @if ($quoteComposerOpen)
 <livewire:posts.composer
 mode="modal"
 context-type="quote-post"
 :context-id="$postId"
 :quote-post-id="$postId"
 :key="'post-quote-composer-'.$postId.'-'.$quoteComposerInstance"
 />
 @endif
</div>
