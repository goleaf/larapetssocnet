<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public int $profileUserId;

    public function mount(int $profileUserId): void
    {
        $this->profileUserId = $profileUserId;
    }

    /**
     * @return array{profileUser: User, scheduledPosts: Collection<int, Post>}
     */
    public function viewData(): array
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User && $viewer->is($profileUser), 404);

        return [
            'profileUser' => $profileUser,
            'scheduledPosts' => Post::recentScheduledForProfileOwner($profileUser),
        ];
    }

    private function profileUser(): User
    {
        return User::query()->whereKey($this->profileUserId)->firstOrFail();
    }

    private function viewer(): ?User
    {
        $viewer = auth()->user();

        return $viewer instanceof User ? $viewer : null;
    }
};
?>

@placeholder
<div data-ui="profile-tab-panel-loading" id="profile-panel-scheduled" aria-busy="true">
 <x-ui.card>
 <div class="space-y-4">
 <div class="h-4 w-36 animate-pulse rounded-full bg-cream"></div>
 <div class="h-32 animate-pulse rounded-xl bg-cream"></div>
 </div>
 </x-ui.card>
</div>
@endplaceholder

@php
 $data = $this->viewData();
@endphp

<div data-ui="profile-tab-panel" id="profile-panel-scheduled">
 <x-ui.card>
 <h2 class="mb-4 text-base font-bold font-display text-bark">Scheduled posts</h2>
 <div class="space-y-4">
 @forelse ($data['scheduledPosts'] as $post)
 <x-post-card :post="$post" context="profile"/>
 @empty
 <x-ui.empty-state icon="🗓️" title="No scheduled posts" description="Scheduled posts you create will appear here before they publish."/>
 @endforelse
 </div>
 </x-ui.card>
</div>
