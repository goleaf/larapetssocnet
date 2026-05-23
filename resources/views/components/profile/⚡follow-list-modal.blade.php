<?php

use App\Enums\FollowAbility;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public int $profileUserId;

    public string $mode;

    public ?int $total = null;

    public string $search = '';

    private const MODES = ['followers', 'following'];

    private const PREVIEW_LIMIT = 12;

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

        abort_if(Gate::denies($this->ability(), $profileUser), 403);

        $query = $this->followListQuery($profileUser, $this->viewer());
        $searchTerm = $this->searchTerm();

        if ($searchTerm !== '') {
            $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                $searchQuery
                    ->where('users.name', 'like', '%'.$searchTerm.'%')
                    ->orWhere('users.username', 'like', '%'.$searchTerm.'%');
            });
        }

        $users = $query
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        return [
            'profileUser' => $profileUser,
            'users' => $users,
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

    private function profileUser(): User
    {
        return User::query()->findOrFail($this->profileUserId);
    }

    private function viewer(): ?User
    {
        $viewer = request()->user() ?: auth()->user();

        return $viewer instanceof User ? $viewer : null;
    }

    private function ability(): FollowAbility
    {
        return $this->mode === 'followers'
            ? FollowAbility::ViewFollowers
            : FollowAbility::ViewFollowing;
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

    private function searchTerm(): string
    {
        return trim($this->search);
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

@php($data = $this->viewData())

<x-ui.modal
id="{{ $data['modalId'] }}"
name="{{ $data['modalId'] }}"
title="{{ $data['title'] }}"
description="{{ $data['description'] }}"
size="lg"
data-ui="{{ $data['modalId'] }}"
>
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

<div class="max-h-[28rem] overflow-y-auto pr-1" data-ui="{{ $data['modalId'] }}-list">
@forelse ($data['users'] as $listedUser)
<a href="{{ route('profile.show', ['user'=> $listedUser]) }}"
data-ui="{{ $data['modalId'] }}-user"
class="flex min-h-16 items-center gap-3 rounded-[var(--radius-soft)] px-3 py-2 transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
<x-ui.avatar :src="$listedUser->avatar_url" :name="$listedUser->name" size="md"/>
<span class="min-w-0">
<span class="block truncate text-sm font-semibold text-bark">{{ $listedUser->name }}</span>
<span class="block truncate text-xs text-fur">&#64;{{ $listedUser->username }}</span>
</span>
</a>
@empty
<x-ui.empty-state icon="" :title="$data['emptyTitle']" :description="$data['emptyDescription']" class="py-10"/>
@endforelse
</div>

<x-slot name="footer">
<x-ui.button :href="$data['viewAllUrl']" variant="outline" size="sm" class="min-h-11">
{{ $data['viewAllLabel'] }}
</x-ui.button>
</x-slot>
</x-ui.modal>
