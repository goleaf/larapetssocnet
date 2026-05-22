<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\ProfileVisibilityService;
use App\Services\VisibilityService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public int $profileUserId;

    public function mount(int $profileUserId): void
    {
        $this->profileUserId = $profileUserId;
    }

    /**
     * @return array{
     *     profileUser: User,
     *     isOwner: bool,
     *     canViewContent: bool,
     *     posts: LengthAwarePaginator|Collection<int, Post>,
     *     privatePosts: Collection<int, Post>,
     *     privateCount: int,
     *     draftPosts: Collection<int, Post>,
     *     draftCount: int,
     *     scheduledPosts: Collection<int, Post>,
     *     scheduledCount: int
     * }
     */
    public function viewData(): array
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();
        $isOwner = $viewer instanceof User && $viewer->is($profileUser);
        $canViewContent = app(ProfileVisibilityService::class)->canViewFullProfile($viewer, $profileUser);

        $posts = $canViewContent
            ? Post::paginateProfileTimeline($profileUser, $viewer)
            : collect();

        if ($posts instanceof LengthAwarePaginator) {
            $posts->fragment('posts');
        }

        $privatePosts = collect();
        $privateCount = 0;
        $draftPosts = collect();
        $draftCount = 0;
        $scheduledPosts = collect();
        $scheduledCount = 0;

        if ($isOwner && $canViewContent) {
            $privatePosts = Post::recentPrivateForProfileOwner($profileUser)
                ->filter(fn (Post $post): bool => app(VisibilityService::class)->canViewOnProfile($viewer, $post))
                ->values();
            $privateCount = Post::privateCountForProfile($profileUser);
            $draftPosts = Post::recentDraftsForProfileOwner($profileUser);
            $draftCount = Post::draftCountForProfile($profileUser);
            $scheduledPosts = Post::recentScheduledForProfileOwner($profileUser);
            $scheduledCount = Post::scheduledCountForProfile($profileUser);
        }

        return [
            'profileUser' => $profileUser,
            'isOwner' => $isOwner,
            'canViewContent' => $canViewContent,
            'posts' => $posts,
            'privatePosts' => $privatePosts,
            'privateCount' => $privateCount,
            'draftPosts' => $draftPosts,
            'draftCount' => $draftCount,
            'scheduledPosts' => $scheduledPosts,
            'scheduledCount' => $scheduledCount,
        ];
    }

    private function profileUser(): User
    {
        return User::query()
            ->whereKey($this->profileUserId)
            ->with('media')
            ->firstOrFail();
    }

    private function viewer(): ?User
    {
        $viewer = auth()->user();

        return $viewer instanceof User ? $viewer : null;
    }
};
?>

@placeholder
<div data-ui="profile-tab-panel-loading" id="profile-panel-posts" aria-busy="true">
 <x-ui.card>
 <div class="space-y-4">
 <div class="h-4 w-40 animate-pulse rounded-full bg-cream"></div>
 <div class="h-32 animate-pulse rounded-xl bg-cream"></div>
 <div class="h-32 animate-pulse rounded-xl bg-cream"></div>
 </div>
 </x-ui.card>
</div>
@endplaceholder

@php
 $data = $this->viewData();
@endphp

<div data-ui="profile-tab-panel" id="profile-panel-posts" class="space-y-4">
 @if (! $data['canViewContent'])
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="This profile is private"
 description="Follow {{ $data['profileUser']->name }} to view posts."/>
 </x-ui.card>
 @else
 @if ($data['isOwner'])
 <x-ui.card>
 <div class="flex items-center gap-3">
 <x-ui.avatar :src="$data['profileUser']->avatar_url" :name="$data['profileUser']->name" size="md"/>
 <a href="{{ route('posts.create') }}"
 class="flex min-h-11 w-full items-center rounded-full border border-whisker/40 bg-cream px-4 py-2 text-left text-sm text-fur transition-colors hover:bg-paw-light/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 What's on your mind, {{ $data['profileUser']->name }}?
 </a>
 </div>
 <div class="mt-3 grid grid-cols-3 gap-2">
 <a href="{{ route('posts.create') }}"
 class="flex min-h-11 items-center justify-center rounded-lg border border-whisker/30 bg-warm-white px-3 py-2 text-center text-xs font-semibold text-fur transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">📷
 Photo</a>
 <a href="{{ route('posts.create') }}"
 class="flex min-h-11 items-center justify-center rounded-lg border border-whisker/30 bg-warm-white px-3 py-2 text-center text-xs font-semibold text-fur transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">🐾
 Pet update</a>
 <a href="{{ route('posts.create') }}"
 class="flex min-h-11 items-center justify-center rounded-lg border border-whisker/30 bg-warm-white px-3 py-2 text-center text-xs font-semibold text-fur transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">🎉
 Life event</a>
 </div>
 </x-ui.card>
 @endif

 @forelse ($data['posts'] as $post)
 <x-post-card :post="$post" context="profile"/>

 @if ($data['isOwner'])
 <div class="-mt-2 flex items-center justify-end gap-2">
 @if ($post->is_pinned)
 <form method="POST" action="{{ route('posts.unpin', $post) }}">
 @csrf
 @method('DELETE')
 <x-ui.button type="submit" variant="ghost" size="xs" class="min-h-9">Unpin</x-ui.button>
 </form>
 @else
 <form method="POST" action="{{ route('posts.pin', $post) }}">
 @csrf
 <x-ui.button type="submit" variant="secondary" size="xs" class="min-h-9">Pin to Profile</x-ui.button>
 </form>
 @endif
 </div>
 @endif
 @empty
 <x-ui.empty-state icon="📝" title="No posts yet" description="No posts published yet."/>
 @endforelse

 @if ($data['isOwner'] && $data['privateCount'] > 0)
 <x-ui.card>
 <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-fur">
 <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
 stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
 </svg>
 Private posts
 <x-ui.badge variant="default" size="sm">{{ $data['privateCount'] }}</x-ui.badge>
 </h3>

 <div class="space-y-4">
 @foreach ($data['privatePosts'] as $post)
 <x-post-card :post="$post" context="profile"/>
 @endforeach
 </div>
 </x-ui.card>
 @endif

 @if ($data['isOwner'] && ($data['draftCount'] > 0 || $data['scheduledCount'] > 0))
 <x-ui.card>
 <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-fur">
 <span aria-hidden="true">🗂️</span>
 Drafts & Scheduled
 @if ($data['draftCount'] > 0)
 <x-ui.badge variant="default" size="sm">{{ $data['draftCount'] }} drafts</x-ui.badge>
 @endif
 @if ($data['scheduledCount'] > 0)
 <x-ui.badge variant="warning" size="sm">{{ $data['scheduledCount'] }} scheduled</x-ui.badge>
 @endif
 </h3>

 <div class="space-y-4">
 @foreach ($data['draftPosts'] as $post)
 <x-post-card :post="$post" context="profile"/>
 @endforeach

 @foreach ($data['scheduledPosts'] as $post)
 <x-post-card :post="$post" context="profile"/>
 @endforeach
 </div>
 </x-ui.card>
 @endif

 @if (method_exists($data['posts'],'hasPages') && $data['posts']->hasPages())
 <x-ui.card>
 <x-ui.pagination :paginator="$data['posts']"/>
 </x-ui.card>
 @endif
 @endif
</div>
