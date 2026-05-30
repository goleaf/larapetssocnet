<?php

use App\Enums\GroupMemberStatus;
use App\Models\Identity\User;
use App\Services\FeedService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
    /**
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        $user = $this->viewer();

        $yourGroups = $user->groups()
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('group_members.status')
                    ->orWhereIn('group_members.status', GroupMemberStatus::activeValues());
            })
            ->orderByDesc('groups.members_count')
            ->limit(6)
            ->get();

        return array_merge(app(FeedService::class)->getSidebarData($user), [
            'yourGroups' => $yourGroups,
        ]);
    }

    private function viewer(): User
    {
        $viewer = auth()->user();

        abort_unless($viewer instanceof User, 403);

        return $viewer;
    }
};
?>

@placeholder
    <aside class="hidden space-y-4 xl:block xl:sticky xl:top-24 xl:self-start" data-ui="feed-right-sidebar-skeleton">
        @for ($cardIndex = 0; $cardIndex < 3; $cardIndex++)
            <x-ui.card padding="base" class="animate-pulse">
                <div class="flex items-center justify-between">
                    <div class="h-4 w-28 rounded-full bg-whisker/30"></div>
                    <div class="h-3 w-12 rounded-full bg-whisker/20"></div>
                </div>
                <div class="mt-4 space-y-3">
                    @for ($itemIndex = 0; $itemIndex < 4; $itemIndex++)
                        <div class="h-10 rounded-[var(--radius-soft)] bg-whisker/20"></div>
                    @endfor
                </div>
            </x-ui.card>
        @endfor
    </aside>
@endplaceholder

@php($data = $this->viewData())

<aside class="hidden space-y-4 xl:block xl:sticky xl:top-24 xl:self-start" data-ui="feed-right-sidebar">
    @if (collect($data['suggestions'] ?? [])->isNotEmpty())
        <x-ui.card padding="base">
            <div class="mb-4 flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-fur">{{ __('feed.suggestions_title') }}</p>
                <a href="{{ route('search.index', ['type' => 'users']) }}" class="text-xs font-semibold text-paw hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">{{ __('feed.see_all') }}</a>
            </div>

            <div class="space-y-3">
                @foreach (collect($data['suggestions'] ?? []) as $suggested)
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0 flex items-center gap-3">
                            <a href="{{ route('profile.show', ['user' => $suggested]) }}" class="shrink-0 rounded-[var(--radius-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                                <x-ui.avatar :src="$suggested->avatar_url" :name="$suggested->name" :user="$suggested" size="md"/>
                            </a>
                            <div class="min-w-0">
                                <a href="{{ route('profile.show', ['user' => $suggested]) }}" class="block min-h-6 truncate text-sm font-semibold text-bark hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                                    {{ $suggested->name }}
                                </a>
                                <p class="truncate text-xs text-fur">&#64;{{ $suggested->username }}</p>
                                @if ($suggested->suggestion_reason)
                                    <p class="truncate text-[11px] text-fur/70">{{ $suggested->suggestion_reason }}</p>
                                @endif
                            </div>
                        </div>
                        <x-follow-button :user="$suggested" follow-status="none" size="sm"/>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    @if (collect($data['trending'] ?? [])->isNotEmpty())
        <x-ui.card padding="base" data-ui="feed-trending-hashtags">
            <div class="mb-4 flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-fur">{{ __('feed.trending_title') }}</p>
                <span class="text-xs text-fur">{{ __('feed.trending_window') }}</span>
            </div>
            <div class="space-y-2">
                @foreach (collect($data['trending'] ?? [])->take(10) as $hashtag)
                    <a href="{{ route('hashtags.show', ['hashtag' => $hashtag->slug ?? $hashtag->normalized_name]) }}" class="ui-list-item flex items-center justify-between gap-3 px-3 py-2 group">
                        <span class="truncate text-sm font-semibold text-bark transition-colors group-hover:text-paw">#{{ $hashtag->name }}</span>
                        <span class="text-xs text-fur">{{ number_format((int) $hashtag->posts_count) }}</span>
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    @if (collect($data['upcomingBirthdays'] ?? [])->isNotEmpty())
        <x-ui.card padding="base" data-ui="feed-upcoming-birthdays">
            <div class="mb-4 flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-fur">{{ __('feed.birthdays_title') }}</p>
                <span class="text-xs text-fur">{{ __('feed.birthdays_window') }}</span>
            </div>
            <div class="space-y-3">
                @foreach (collect($data['upcomingBirthdays'] ?? []) as $birthdayPet)
                    <a href="{{ route('pets.show', ['pet' => $birthdayPet->slug ?? $birthdayPet->getKey()]) }}" class="ui-list-item flex items-center gap-3 px-3 py-2 group">
                        <x-ui.avatar :src="$birthdayPet->avatar_url" :name="$birthdayPet->name" size="sm"/>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-bark transition-colors group-hover:text-paw">{{ $birthdayPet->name }}</p>
                            <p class="truncate text-xs text-fur">
                                {{ trans_choice('feed.birthdays_days_until', (int) $birthdayPet->getAttribute('days_until_birthday'), ['count' => (int) $birthdayPet->getAttribute('days_until_birthday')]) }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    <x-ui.card padding="base">
        <div class="mb-4 flex items-center justify-between">
            <p class="text-xs font-bold uppercase tracking-wider text-fur">{{ __('feed.groups_title') }}</p>
            <a href="{{ route('groups.index', ['privacy' => 'joined']) }}" class="text-xs font-semibold text-paw hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">{{ __('feed.browse') }}</a>
        </div>

        <div class="space-y-2.5">
            @forelse (collect($data['yourGroups'] ?? []) as $group)
                <a href="{{ route('groups.show', filled((string) ($group->slug ?? '')) ? $group->slug : $group->id) }}" class="ui-list-item flex items-center justify-between px-3 py-2 group">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-bark transition-colors group-hover:text-paw">{{ $group->name }}</p>
                        <p class="truncate text-xs text-fur">{{ \Illuminate\Support\Str::headline((string) ($group->privacy ?? 'public')) }}</p>
                    </div>
                    <span class="rounded-full bg-whisker/20 px-2 py-0.5 text-xs font-medium text-fur">{{ number_format((int) ($group->members_count ?? 0)) }}</span>
                </a>
            @empty
                <p class="text-sm text-fur">{{ __('feed.no_groups') }}</p>
            @endforelse
        </div>

        @auth
            <x-ui.button href="{{ route('groups.create') }}" variant="primary" class="mt-4 w-full justify-center">{{ __('feed.create_group') }}</x-ui.button>
        @endauth
    </x-ui.card>
</aside>
