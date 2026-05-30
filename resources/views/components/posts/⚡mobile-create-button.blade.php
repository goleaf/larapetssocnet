<?php

use App\Models\Identity\User;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $composerOpen = false;

    public int $composerInstance = 0;

    public function openComposer(): void
    {
        abort_unless(auth()->user() instanceof User, 403);

        $this->composerOpen = true;
        $this->composerInstance++;
    }

    #[On('post-created')]
    #[On('post-composer-closed')]
    public function closeComposer(): void
    {
        $this->composerOpen = false;
    }
};
?>

<div>
 <button
 type="button"
 wire:click="openComposer"
 class="fixed bottom-24 right-4 z-40 inline-flex h-14 w-14 items-center justify-center rounded-full bg-paw text-2xl font-bold text-white shadow-card transition hover:bg-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw lg:hidden"
 aria-label="Create post"
 data-ui="mobile-post-create-fab"
 >
 <span aria-hidden="true">+</span>
 </button>

 @if ($composerOpen)
 <livewire:posts.composer
 mode="modal"
 context-type="mobile-fab"
 :key="'mobile-post-composer-'.$composerInstance"
 />
 @endif
</div>
