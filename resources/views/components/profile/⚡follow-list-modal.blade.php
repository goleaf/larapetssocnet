<?php

use App\Enums\FollowAbility;
use App\Models\Identity\User;
use App\Models\Social\Follow;
use App\Services\FollowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Renderless;
use Livewire\Component;

new class extends Component
{
    public int $profileUserId;

    public string $mode;

    public ?int $total = null;

    public string $search = '';

    public int $page = 1;

    private const MODES = ['followers', 'following'];

    private const PAGE_SIZE = 20;

    public function mount(int $profileUserId, string $mode, ?int $total = null): void
    {
        abort_unless(in_array($mode, self::MODES, true), 404);

        $this->profileUserId = $profileUserId;
        $this->mode = $mode;
        $this->total = $total;
    }

    /**
     * @return array{
     *     profileUser: User,
     *     users: Collection<int, User>,
     *     viewer: User|null,
     *     followStatusMap: array<int, string>,
     *     hasMore: bool,
     *     isLocked: bool,
     *     lockedFollowStatus: string,
     *     lockedCanRequestFollow: bool,
     *     modalId: string,
     *     title: string,
     *     description: string,
     *     searchPlaceholder: string,
     *     emptyTitle: string,
     *     emptyDescription: string,
     *     viewAllLabel: string,
     *     viewAllUrl: string
     * }
     */
    public function viewData(): array
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();

        if (! $this->canViewList($profileUser, $viewer)) {
            abort_unless($this->canRenderLockedState($profileUser, $viewer), 403);

            return [
                'profileUser' => $profileUser,
                'users' => collect(),
                'viewer' => $viewer,
                'followStatusMap' => [],
                'hasMore' => false,
                'isLocked' => true,
                'lockedFollowStatus' => $viewer instanceof User ? $viewer->getFollowStatus($profileUser) : 'none',
                'lockedCanRequestFollow' => $profileUser->canBeFollowedBy($viewer),
                'modalId' => $this->modalId(),
                'title' => $this->title(),
                'description' => "Visibility is limited by this account's privacy settings.",
                'searchPlaceholder' => $this->searchPlaceholder(),
                'emptyTitle' => $this->emptyTitle(),
                'emptyDescription' => $this->emptyDescription(),
                'viewAllLabel' => $this->viewAllLabel(),
                'viewAllUrl' => route($this->viewAllRouteName(), ['user' => $profileUser]),
            ];
        }

        $query = $this->followListQuery($profileUser, $viewer);
        $searchTerm = $this->searchTerm();

        if ($searchTerm !== '') {
            $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                $searchQuery
                    ->where('users.name', 'like', '%'.$searchTerm.'%')
                    ->orWhere('users.username', 'like', '%'.$searchTerm.'%');
            });
        }

        if ($viewer instanceof User) {
            $viewerFollowingIds = Follow::query()
                ->select('following_id')
                ->where('follower_id', $viewer->getKey())
                ->where('status', 'accepted');

            $query->withCount([
                'acceptedFollowers as mutual_followers_count' => function (Builder $mutualQuery) use ($viewerFollowingIds): void {
                    $mutualQuery->whereIn('follows.follower_id', $viewerFollowingIds);
                },
            ]);
        }

        $loadedLimit = $this->loadedLimit();
        $users = $query
            ->limit($loadedLimit + 1)
            ->get();
        $hasMore = $users->count() > $loadedLimit;
        $users = $users->take($loadedLimit)->values();

        return [
            'profileUser' => $profileUser,
            'users' => $users,
            'viewer' => $viewer,
            'followStatusMap' => $this->followStatusMap($users, $viewer),
            'hasMore' => $hasMore,
            'isLocked' => false,
            'lockedFollowStatus' => 'none',
            'lockedCanRequestFollow' => false,
            'modalId' => $this->modalId(),
            'title' => $this->title(),
            'description' => $this->description($profileUser),
            'searchPlaceholder' => $this->searchPlaceholder(),
            'emptyTitle' => $this->emptyTitle(),
            'emptyDescription' => $this->emptyDescription(),
            'viewAllLabel' => $this->viewAllLabel(),
            'viewAllUrl' => route($this->viewAllRouteName(), ['user' => $profileUser]),
        ];
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function loadMore(): void
    {
        $this->page++;
    }

    /**
     * @return array{follow_status: string, follower_count: int}
     */
    #[Renderless]
    public function requestFollow(FollowService $followService): array
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $target = User::query()
            ->active()
            ->notBlockedFor($viewer)
            ->whereKey($this->profileUserId)
            ->firstOrFail();

        $status = $viewer->getFollowStatus($target);

        if ($status === 'none') {
            Gate::forUser($viewer)->authorize(FollowAbility::Follow, $target);

            $status = $followService->follow($viewer, $target);
        }

        return [
            'follow_status' => $status,
            'follower_count' => (int) $target->fresh()->followers_count,
        ];
    }

    /**
     * @return array{follow_status: string, follower_count: int}
     */
    #[Renderless]
    public function toggleFollow(int $targetUserId, FollowService $followService): array
    {
        $viewer = $this->viewer();

        abort_unless($viewer instanceof User, 401);

        $target = User::query()
            ->active()
            ->notBlockedFor($viewer)
            ->whereKey($targetUserId)
            ->firstOrFail();

        $currentStatus = $viewer->getFollowStatus($target);

        if (in_array($currentStatus, ['following', 'pending'], true)) {
            Gate::forUser($viewer)->authorize(FollowAbility::Unfollow, $target);

            $followService->unfollow($viewer, $target);
            $status = 'none';
        } else {
            Gate::forUser($viewer)->authorize(FollowAbility::Follow, $target);

            $status = $followService->follow($viewer, $target);
        }

        return [
            'follow_status' => $status,
            'follower_count' => (int) $target->fresh()->followers_count,
        ];
    }

    private function profileUser(): User
    {
        return User::query()->findOrFail($this->profileUserId);
    }

    private function viewer(): ?User
    {
        $viewer = request()->user() ?: auth()->user();

        return $viewer instanceof User ? $viewer : null;
    }

    private function canViewList(User $profileUser, ?User $viewer): bool
    {
        return $this->mode === 'followers'
            ? $profileUser->canViewFollowersList($viewer)
            : $profileUser->canViewFollowingList($viewer);
    }

    private function canRenderLockedState(User $profileUser, ?User $viewer): bool
    {
        if (! (bool) $profileUser->is_private || $profileUser->isUnavailableForProfile()) {
            return false;
        }

        if (! $viewer instanceof User) {
            return true;
        }

        if ($viewer->isUnavailableForProfile() || $viewer->hasBlockingRelationshipWith($profileUser)) {
            return false;
        }

        return ! $viewer->is($profileUser);
    }

    /**
     * @return BelongsToMany<User, User>
     */
    private function followListQuery(User $profileUser, ?User $viewer): BelongsToMany
    {
        $query = $this->mode === 'followers'
            ? $profileUser->acceptedFollowers()
            : $profileUser->acceptedFollowing();

        return $query
            ->active()
            ->notBlockedFor($viewer)
            ->with('media')
            ->orderBy('users.name');
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int, string>
     */
    private function followStatusMap(Collection $users, ?User $viewer): array
    {
        if (! $viewer instanceof User) {
            return [];
        }

        return app(FollowService::class)->followStatusMap($viewer, $users);
    }

    private function searchTerm(): string
    {
        return trim($this->search);
    }

    private function loadedLimit(): int
    {
        return max(1, $this->page) * self::PAGE_SIZE;
    }

    private function modalId(): string
    {
        return $this->mode === 'followers'
            ? 'profile-followers-modal'
            : 'profile-following-modal';
    }

    private function title(): string
    {
        return $this->mode === 'followers' ? 'Followers' : 'Following';
    }

    private function description(User $profileUser): string
    {
        $total = $this->total ?? (int) ($this->mode === 'followers'
            ? $profileUser->followers_count
            : $profileUser->following_count);

        if ($this->mode === 'followers') {
            return number_format($total).' '.Str::plural('follower', $total);
        }

        return number_format($total).' following';
    }

    private function emptyTitle(): string
    {
        if ($this->searchTerm() !== '') {
            return 'No matches found';
        }

        return $this->mode === 'followers'
            ? 'No followers yet'
            : 'Not following anyone yet';
    }

    private function emptyDescription(): string
    {
        if ($this->searchTerm() !== '') {
            return 'Try another name or username.';
        }

        return $this->mode === 'followers'
            ? 'Followers will appear here.'
            : 'Profiles followed by this user will appear here.';
    }

    private function searchPlaceholder(): string
    {
        return $this->mode === 'followers'
            ? 'Search followers by name or username'
            : 'Search following by name or username';
    }

    private function viewAllLabel(): string
    {
        return $this->mode === 'followers'
            ? 'View all followers'
            : 'View all following';
    }

    private function viewAllRouteName(): string
    {
        return $this->mode === 'followers'
            ? 'profile.followers'
            : 'profile.following';
    }
};
?>

@php
 $data = $this->viewData();
@endphp

<x-ui.modal
id="{{ $data['modalId'] }}"
name="{{ $data['modalId'] }}"
title="{{ $data['title'] }}"
description="{{ $data['description'] }}"
size="lg"
data-ui="{{ $data['modalId'] }}"
>
@if ($data['isLocked'])
<div class="flex min-h-[18rem] flex-col items-center justify-center gap-4 px-4 py-10 text-center" data-ui="{{ $data['modalId'] }}-locked-state">
<div class="flex h-12 w-12 items-center justify-center rounded-full bg-cream text-fur" aria-hidden="true">
<svg data-ui="{{ $data['modalId'] }}-locked-icon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
<path stroke-linecap="round" stroke-linejoin="round" d="M7 10V8a5 5 0 0 1 10 0v2"/>
<path stroke-linecap="round" stroke-linejoin="round" d="M6.5 10h11A1.5 1.5 0 0 1 19 11.5v7A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-7A1.5 1.5 0 0 1 6.5 10Z"/>
</svg>
</div>
<div class="max-w-sm space-y-2">
<h3 class="font-display text-xl font-bold text-bark">This list is private</h3>
<p class="text-sm leading-6 text-fur">Follow &#64;{{ $data['profileUser']->username }} to request access to private profile details.</p>
</div>

@if ($data['viewer'] instanceof User && ! $data['viewer']->is($data['profileUser']))
<div
x-data="{
    status: @js($data['lockedFollowStatus']),
    canRequest: @js($data['lockedCanRequestFollow']),
    loading: false,
    get label() {
        if (this.loading) {
            return 'Sending...'
        }

        return { following: 'Following', pending: 'Requested', none: 'Request to Follow' }[this.status] ?? 'Request to Follow'
    },
    async request() {
        if (this.loading || ! this.canRequest || this.status !== 'none') {
            return
        }

        this.loading = true

        try {
            const result = await $wire.requestFollow()
            this.status = result?.follow_status ?? this.status
        } finally {
            this.loading = false
        }
    },
}"
data-ui="{{ $data['modalId'] }}-locked-follow-state">
<x-ui.button
type="button"
variant="primary"
size="sm"
@click="request()"
x-bind:disabled="loading || !canRequest || status !== 'none'"
x-bind:aria-busy="loading.toString()"
x-bind:aria-label="label + ' {{ '@'.$data['profileUser']->username }}'"
class="min-h-11 min-w-36 justify-center"
data-ui="{{ $data['modalId'] }}-locked-follow-action">
<span x-text="label">{{ $data['lockedFollowStatus'] === 'pending' ? 'Requested' : ($data['lockedFollowStatus'] === 'following' ? 'Following' : 'Request to Follow') }}</span>
</x-ui.button>
</div>
@elseif (! ($data['viewer'] instanceof User) && Route::has('profile.guest-follow'))
<form method="POST" action="{{ route('profile.guest-follow', ['user'=> $data['profileUser']]) }}" data-ui="{{ $data['modalId'] }}-locked-guest-follow-form">
@csrf
<x-ui.button type="submit" variant="primary" size="sm" class="min-h-11 min-w-36 justify-center" data-ui="{{ $data['modalId'] }}-locked-follow-action">
Request to Follow
</x-ui.button>
</form>
@endif
</div>
@else
<div class="pb-4" data-ui="{{ $data['modalId'] }}-search">
<label for="{{ $data['modalId'] }}-search-input" class="sr-only">{{ $data['searchPlaceholder'] }}</label>
<div class="relative">
<span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-fur" aria-hidden="true">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
<path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
</svg>
</span>
<input
id="{{ $data['modalId'] }}-search-input"
type="search"
wire:model.live.debounce.300ms="search"
placeholder="{{ $data['searchPlaceholder'] }}"
autocomplete="off"
class="form-input h-[var(--control-height-md)] w-full pl-10 pr-10 text-sm focus:border-paw"
data-ui="{{ $data['modalId'] }}-search-input"
/>
<span wire:loading wire:target="search" class="absolute inset-y-0 right-0 flex items-center pr-3 text-fur" aria-label="Searching">
<x-ui.loading-spinner size="sm" color="fur" label="Searching"/>
</span>
</div>
</div>

<div
x-data="{
    loadingMore: false,
    async loadMoreEntries() {
        if (this.loadingMore) {
            return
        }

        this.loadingMore = true

        try {
            await $wire.loadMore()
        } finally {
            this.loadingMore = false
        }
    },
}"
data-ui="{{ $data['modalId'] }}-infinite-scroll">
<div x-ref="scrollContainer" class="max-h-[28rem] overflow-y-auto pr-1" data-ui="{{ $data['modalId'] }}-list">
@forelse ($data['users'] as $listedUser)
@php
 $listedDisplayName = (string) ($listedUser->display_name ?: $listedUser->name);
 $listedBio = \Illuminate\Support\Str::squish((string) $listedUser->bio);
 $listedMutualCount = (int) ($listedUser->mutual_followers_count ?? 0);
 $listedFollowStatus = (string) ($data['followStatusMap'][$listedUser->getKey()] ?? 'none');
 $canToggleListedUser = $data['viewer'] instanceof User && ! $data['viewer']->is($listedUser);
@endphp
<article
wire:key="{{ $data['modalId'] }}-user-{{ $listedUser->getKey() }}"
data-ui="{{ $data['modalId'] }}-user"
class="flex min-h-24 items-start gap-3 rounded-[var(--radius-soft)] px-3 py-3 transition-colors hover:bg-cream">
<a href="{{ route('profile.show', ['user'=> $listedUser]) }}"
class="mt-0.5 shrink-0 rounded-pill focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
aria-label="View {{ $listedDisplayName }} profile">
<x-ui.avatar :src="$listedUser->avatar_url" :name="$listedDisplayName" :alt="$listedDisplayName.' profile avatar'" size="profile-list"/>
</a>
<div class="min-w-0 flex-1">
<div class="flex min-w-0 items-center gap-1.5">
<a href="{{ route('profile.show', ['user'=> $listedUser]) }}"
class="truncate text-sm font-bold text-bark transition-colors hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
data-ui="{{ $data['modalId'] }}-display-name">
{{ $listedDisplayName }}
</a>
@if ($listedUser->profile_verified)
<x-ui.verified-badge tooltip-id="{{ $data['modalId'] }}-verified-{{ $listedUser->getKey() }}"/>
@endif
</div>
<a href="{{ route('profile.show', ['user'=> $listedUser]) }}"
class="mt-0.5 block truncate text-xs font-medium text-fur transition-colors hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
data-ui="{{ $data['modalId'] }}-username">
&#64;{{ $listedUser->username }}
</a>
<p class="mt-1 truncate text-xs leading-5 text-bark/80" data-ui="{{ $data['modalId'] }}-bio">
{{ $listedBio !== '' ? $listedBio : 'No bio yet.' }}
</p>
@if ($listedMutualCount > 0)
<p class="mt-1 text-xs font-medium text-fur" data-ui="{{ $data['modalId'] }}-mutual-count">
{{ number_format($listedMutualCount) }} mutual
</p>
@endif
</div>
<div class="ml-auto shrink-0 self-center">
@if ($canToggleListedUser)
<div
x-data="{
    status: @js($listedFollowStatus),
    loading: false,
    hovered: false,
    get label() {
        if (this.status === 'following' && this.hovered) {
            return 'Unfollow'
        }

        return { following: 'Following', pending: 'Requested', none: 'Follow' }[this.status] ?? 'Follow'
    },
    get buttonClass() {
        if (this.status === 'following') {
            return 'border-rose/40 text-rose hover:bg-rose-light/40'
        }

        if (this.status === 'pending') {
            return 'border-whisker/40 bg-cream text-fur'
        }

        return 'border-transparent bg-paw text-white hover:bg-paw-dark'
    },
    async toggle() {
        if (this.loading) {
            return
        }

        const previousStatus = this.status
        this.loading = true

        try {
            const result = await $wire.toggleFollow(@js($listedUser->getKey()))
            this.status = result?.follow_status ?? this.status
        } catch {
            this.status = previousStatus
        } finally {
            this.loading = false
            this.hovered = false
        }
    },
}"
data-ui="{{ $data['modalId'] }}-follow-state">
<x-ui.button
type="button"
variant="outline"
size="sm"
@click="toggle()"
@mouseenter="hovered = true"
@mouseleave="hovered = false"
x-bind:disabled="loading"
x-bind:aria-busy="loading.toString()"
x-bind:aria-pressed="(status === 'following').toString()"
x-bind:aria-label="label + ' {{ '@'.$listedUser->username }}'"
x-bind:class="buttonClass"
class="min-w-24 justify-center"
data-ui="{{ $data['modalId'] }}-follow-toggle">
<span x-show="loading" x-cloak>...</span>
<span x-show="!loading" x-text="label">{{ $listedFollowStatus === 'following' ? 'Following' : ($listedFollowStatus === 'pending' ? 'Requested' : 'Follow') }}</span>
</x-ui.button>
</div>
@elseif (! ($data['viewer'] instanceof User))
<x-ui.button :href="route('login')" variant="primary" size="sm" class="min-w-24 justify-center" data-ui="{{ $data['modalId'] }}-follow-login">
Follow
</x-ui.button>
@else
<span class="inline-flex min-h-9 items-center rounded-[var(--radius-soft)] px-3 text-xs font-semibold text-fur" data-ui="{{ $data['modalId'] }}-self-label">
You
</span>
@endif
</div>
</article>
@empty
<x-ui.empty-state icon="" :title="$data['emptyTitle']" :description="$data['emptyDescription']" class="py-10"/>
@endforelse
@if ($data['hasMore'])
<div
x-ref="sentinel"
x-init="
    const state = $data
    const observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            state.loadMoreEntries()
        }
    }, { root: $refs.scrollContainer, rootMargin: '160px 0px', threshold: 0 })

    observer.observe($el)
    $cleanup(() => observer.disconnect())
"
class="flex min-h-12 items-center justify-center py-3"
data-ui="{{ $data['modalId'] }}-load-more-sentinel">
<span wire:loading.delay wire:target="loadMore" class="inline-flex items-center gap-2 text-xs font-semibold text-fur">
<x-ui.loading-spinner size="sm" color="fur" label="Loading more {{ strtolower($data['title']) }}"/>
Loading more {{ strtolower($data['title']) }}
</span>
<span wire:loading.remove wire:target="loadMore" class="sr-only">Load more {{ strtolower($data['title']) }}</span>
</div>
@endif
</div>
</div>
@endif

<x-slot name="footer">
@unless ($data['isLocked'])
<x-ui.button :href="$data['viewAllUrl']" variant="outline" size="sm" class="min-h-11">
{{ $data['viewAllLabel'] }}
</x-ui.button>
@endunless
</x-slot>
</x-ui.modal>
