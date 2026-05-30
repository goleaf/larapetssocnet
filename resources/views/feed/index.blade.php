@section('title', __('feed.page_title'))

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('feed.header_title')" :description="__('feed.header_description')" :icon="null">
            <x-slot:action>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button href="{{ route('saved.index') }}" variant="ghost" size="sm">{{ __('feed.saved') }}</x-ui.button>
                    <x-ui.button href="{{ route('explore.index') }}" variant="ghost" size="sm">{{ __('feed.explore') }}</x-ui.button>
                </div>
            </x-slot:action>
        </x-ui.page-header>
    </x-slot>

    <div class="grid gap-4 lg:grid-cols-[16rem_minmax(0,1fr)] xl:grid-cols-[16rem_minmax(0,1fr)_18rem]" data-feed-surface="warm-editorial">
        <aside class="hidden space-y-4 lg:block lg:sticky lg:top-24 lg:self-start" data-ui="feed-left-sidebar">
            <x-ui.card padding="base">
                <div class="flex items-center gap-3">
                    <x-ui.avatar :src="$user->avatar_url" :name="$user->name" :user="$user" size="lg"/>
                    <div class="min-w-0">
                        <a href="{{ route('profile.show', $user) }}" class="block truncate text-sm font-bold text-bark hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                            {{ $user->name }}
                        </a>
                        <p class="truncate text-xs text-fur">&#64;{{ $user->username }}</p>
                    </div>
                </div>

                <dl class="mt-5 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-[var(--radius-soft)] bg-cream px-2 py-3">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-fur">{{ __('feed.stats.followers') }}</dt>
                        <dd class="mt-1 text-base font-bold text-bark">{{ number_format((int) ($user->feed_followers_count ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-[var(--radius-soft)] bg-cream px-2 py-3">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-fur">{{ __('feed.stats.following') }}</dt>
                        <dd class="mt-1 text-base font-bold text-bark">{{ number_format((int) ($user->feed_following_count ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-[var(--radius-soft)] bg-cream px-2 py-3">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-fur">{{ __('feed.stats.pets') }}</dt>
                        <dd class="mt-1 text-base font-bold text-bark">{{ number_format((int) ($user->feed_pets_count ?? 0)) }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card padding="base">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-xs font-bold uppercase tracking-wider text-fur">{{ __('feed.my_pets_title') }}</p>
                    <button type="button" x-on:click.prevent="window.openPetCreateWizard('feed-left-sidebar')" class="text-xs font-semibold text-paw hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                        {{ __('feed.add_pet_shortcut') }}
                    </button>
                </div>

                <div class="space-y-2.5">
                    @forelse ($ownedPets as $pet)
                        <a href="{{ route('pets.show', ['pet' => $pet->slug ?? $pet->getKey()]) }}" class="ui-list-item flex items-center gap-3 px-3 py-2 group">
                            <x-ui.avatar :name="$pet->name" size="sm"/>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-bark transition-colors group-hover:text-paw">{{ $pet->name }}</p>
                                <p class="truncate text-xs text-fur">{{ collect([$pet->species, $pet->breed])->filter()->join(' · ') }}</p>
                            </div>
                        </a>
                    @empty
                        <p class="text-sm leading-6 text-fur">{{ __('feed.no_pets_shortcut') }}</p>
                    @endforelse
                </div>
            </x-ui.card>
        </aside>

        <main class="min-w-0 space-y-4" data-ui="feed-main-column">
            @if ($showWelcomeBanner ?? false)
                <x-ui.card padding="base">
                    <div class="flex items-start gap-3">
                        <x-ui.avatar :src="$user->avatar_url" :name="$user->name" :user="$user" size="lg"/>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-bark">Welcome to PetSocial, {{ \Illuminate\Support\Str::before((string) $user->name, ' ') }}!</p>
                            <p class="mt-1 text-sm leading-6 text-fur">Start exploring by reacting to posts, following more pets, or creating your first post.</p>
                        </div>
                        <form method="POST" action="{{ route('onboarding.welcome-banner.dismiss') }}">
                            @csrf
                            <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-[var(--radius-soft)] text-fur transition hover:bg-cream hover:text-bark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" aria-label="Dismiss welcome banner">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                                    <path d="M5 5l10 10M15 5 5 15"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </x-ui.card>
            @endif

            @if ($showOnboardingPetReminder ?? false)
                <x-ui.card padding="base">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm leading-6 text-fur">Your profile is ready. Add your first pet when you have a moment so your feed can surface better pet matches.</p>
                        <x-ui.button href="#" x-on:click.prevent="window.openPetCreateWizard('onboarding-reminder')" variant="secondary" size="sm">Add a pet</x-ui.button>
                    </div>
                </x-ui.card>
            @endif

            <livewire:feed.stream :source="$source ?? ''" :type="$type ?? ''" lazy />
        </main>

        <aside class="hidden space-y-4 xl:block xl:sticky xl:top-24 xl:self-start" data-ui="feed-right-sidebar">
            @if (collect($suggestions ?? [])->isNotEmpty())
                <x-ui.card padding="base">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-fur">{{ __('feed.suggestions_title') }}</p>
                        <a href="{{ route('search.index', ['type' => 'users']) }}" class="text-xs font-semibold text-paw hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">{{ __('feed.see_all') }}</a>
                    </div>

                    <div class="space-y-3">
                        @foreach (collect($suggestions ?? []) as $suggested)
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

            @if (collect($trending ?? [])->isNotEmpty())
                <x-ui.card padding="base" data-ui="feed-trending-hashtags">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-fur">{{ __('feed.trending_title') }}</p>
                        <span class="text-xs text-fur">{{ __('feed.trending_window') }}</span>
                    </div>
                    <div class="space-y-2">
                        @foreach (collect($trending ?? [])->take(10) as $hashtag)
                            <a href="{{ route('hashtags.show', ['hashtag' => $hashtag->slug ?? $hashtag->normalized_name]) }}" class="ui-list-item flex items-center justify-between gap-3 px-3 py-2 group">
                                <span class="truncate text-sm font-semibold text-bark transition-colors group-hover:text-paw">#{{ $hashtag->name }}</span>
                                <span class="text-xs text-fur">{{ number_format((int) $hashtag->posts_count) }}</span>
                            </a>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif

            @if (collect($upcomingBirthdays ?? [])->isNotEmpty())
                <x-ui.card padding="base" data-ui="feed-upcoming-birthdays">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-fur">{{ __('feed.birthdays_title') }}</p>
                        <span class="text-xs text-fur">{{ __('feed.birthdays_window') }}</span>
                    </div>
                    <div class="space-y-3">
                        @foreach (collect($upcomingBirthdays ?? []) as $birthdayPet)
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
                    @forelse (collect($yourGroups ?? []) as $group)
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
    </div>
</x-app-layout>
