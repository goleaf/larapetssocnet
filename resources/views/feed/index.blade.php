@section('title', 'Community Feed - PetSocial')

<x-app-layout>
    @php
        $yourGroups = collect();

        try {
            if (
                auth()->check()
                && \Illuminate\Support\Facades\Schema::hasTable('groups')
                && \Illuminate\Support\Facades\Schema::hasTable('group_members')
            ) {
                $yourGroups = \App\Models\Group::query()
                    ->whereIn('groups.id', function ($query): void {
                        $query->select('group_members.group_id')
                            ->from('group_members')
                            ->where('group_members.user_id', auth()->id())
                            ->where(function ($statusQuery): void {
                                $statusQuery->whereNull('group_members.status')
                                    ->orWhereIn('group_members.status', ['active', 'accepted']);
                            });
                    })
                    ->orderByDesc('groups.members_count')
                    ->limit(6)
                    ->get();
            }
        } catch (\Throwable) {
            $yourGroups = collect();
        }

        $postMediaError = $errors->first('media') ?: $errors->first('media.*');
        $suggestions = collect($suggestions ?? []);
        $trending = collect($trending ?? []);
        $events = collect($events ?? []);

        $themeMeta = [
            'accessible-soft' => [
                'hero' => 'Gentle palette, readable spacing, and calm contrast.',
                'shell' => 'border-[#e7d7c3] bg-[radial-gradient(circle_at_15%_10%,rgba(232,131,74,0.18),transparent_40%),radial-gradient(circle_at_85%_0%,rgba(74,133,201,0.14),transparent_32%),linear-gradient(180deg,#fffdf9,#fff5ea)]',
                'panel' => 'border-[#eadbc9] bg-white/90',
                'chip' => 'border-[#dcbf9d] bg-[#fff5e8] text-[#5e412d] hover:bg-[#ffe9cd]',
                'chipActive' => 'border-[#c86e39] bg-[#f9d6bc] text-[#5a3119]',
                'kicker' => 'text-[#8a5f44]',
            ],
            'high-contrast' => [
                'hero' => 'Maximum contrast for clear hierarchy and focus states.',
                'shell' => 'border-black bg-[radial-gradient(circle_at_12%_8%,rgba(255,255,255,0.14),transparent_36%),linear-gradient(180deg,#1a1a1a,#050505)] text-white',
                'panel' => 'border-white/80 bg-black/85 text-white',
                'chip' => 'border-white/75 bg-black text-white hover:bg-white hover:text-black',
                'chipActive' => 'border-[#ffe800] bg-[#ffe800] text-black',
                'kicker' => 'text-[#ffe800]',
            ],
            'minimalist-soothe' => [
                'hero' => 'Quiet neutrals with restrained accents and clean rhythm.',
                'shell' => 'border-[#d8dbde] bg-[radial-gradient(circle_at_75%_8%,rgba(122,146,168,0.18),transparent_32%),linear-gradient(180deg,#f9fbfc,#f1f5f7)]',
                'panel' => 'border-[#d4dbe0] bg-[#fcfeff]/95',
                'chip' => 'border-[#c7d0d7] bg-[#eff4f7] text-[#425564] hover:bg-[#e0e9ef]',
                'chipActive' => 'border-[#4e6778] bg-[#4e6778] text-white',
                'kicker' => 'text-[#587082]',
            ],
        ];

        $activeTheme = $themeMeta[$theme] ?? $themeMeta['accessible-soft'];
        $typeFilters = [
            'all' => 'All posts',
            'text' => 'Text',
            'photo' => 'Photos',
            'video' => 'Video',
        ];
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <x-ui.page-header title="Community Feed" subtitle="{{ $themes[$theme] ?? 'Accessible Soft' }}" class="mb-0" />

            <div class="flex items-center gap-2">
                <x-ui.button href="{{ route('saved.index') }}" variant="ghost" size="sm">Saved</x-ui.button>
                <x-ui.button href="{{ route('explore.index') }}" variant="ghost" size="sm">Explore</x-ui.button>
            </div>
        </div>
    </x-slot>

    <section data-feed-theme="{{ $theme }}" @class([
        'relative overflow-hidden rounded-[28px] border p-4 sm:p-6',
        $activeTheme['shell'],
        'dark:border-zinc-700 dark:bg-zinc-900/70 dark:text-zinc-100' => $theme !== 'high-contrast',
    ])>
        <div class="pointer-events-none absolute -left-20 -top-20 h-48 w-48 rounded-full bg-white/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 top-16 h-56 w-56 rounded-full bg-white/10 blur-3xl"></div>

        <div class="relative space-y-6">
            <header class="rounded-3xl border p-4 sm:p-5" @class([$activeTheme['panel'], 'dark:border-zinc-700 dark:bg-zinc-900/70 dark:text-zinc-100' => $theme !== 'high-contrast'])>
                <p class="text-xs font-semibold uppercase tracking-[0.14em]" @class([$activeTheme['kicker'], 'dark:text-zinc-300' => $theme !== 'high-contrast'])>
                    Stitch Integration
                </p>
                <h2 class="mt-2 text-2xl font-bold leading-tight sm:text-3xl">Community Feed</h2>
                <p class="mt-2 max-w-2xl text-sm sm:text-base" @class([$activeTheme['kicker'], 'dark:text-zinc-300' => $theme !== 'high-contrast'])>
                    {{ $activeTheme['hero'] }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2" role="tablist" aria-label="Feed design themes">
                    @foreach ($themes as $themeKey => $themeLabel)
                        @php
                            $themeQuery = array_merge(request()->query(), ['theme' => $themeKey]);
                        @endphp
                        <a
                            href="{{ route('feed.index', $themeQuery) }}"
                            role="tab"
                            aria-selected="{{ $theme === $themeKey ? 'true' : 'false' }}"
                            @class([
                                'rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] transition-colors',
                                $theme === $themeKey ? $activeTheme['chipActive'] : $activeTheme['chip'],
                            ])
                        >
                            {{ $themeLabel }}
                        </a>
                    @endforeach
                </div>
            </header>

            <div class="mt-4 flex flex-col gap-5 max-w-4xl mx-auto">
                <div class="space-y-4">
                    <x-ui.card class="border" :class="$activeTheme['panel']">
                        <x-slot name="header">
                            <x-ui.card-header title="Create a post" subtitle="Share something about your pet today.">
                                <x-slot name="action">
                                    <x-ui.avatar :src="auth()->user()?->avatar_url" :name="auth()->user()?->name" size="md" />
                                </x-slot>
                            </x-ui.card-header>
                        </x-slot>

                        <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf

                            <x-ui.textarea
                                name="body"
                                label="Body"
                                rows="3"
                                placeholder="Share an update about your pet..."
                                :error="$errors->first('body')"
                            >{{ old('body') }}</x-ui.textarea>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <x-ui.select name="pet_id" label="Pet" :error="$errors->first('pet_id')">
                                    <option value="">No pet tag</option>
                                    @foreach (auth()->user()->pets as $pet)
                                        <option value="{{ $pet->id }}" @selected((string) old('pet_id') === (string) $pet->id)>
                                            {{ $pet->name }}
                                        </option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.file-upload
                                    name="media"
                                    label="Media"
                                    multiple
                                    accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime"
                                    :error="$postMediaError"
                                    hint="Upload up to 4 files."
                                />
                            </div>

                            <div class="flex items-center justify-end">
                                <x-ui.button type="submit" variant="primary" size="md">Post</x-ui.button>
                            </div>
                        </form>
                    </x-ui.card>

                    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Feed post filters">
                        @foreach ($typeFilters as $filterKey => $filterLabel)
                            @php
                                $filterQuery = array_merge(request()->query(), ['theme' => $theme]);

                                if ($filterKey === 'all') {
                                    unset($filterQuery['type']);
                                    $isFilterActive = $type === null;
                                } else {
                                    $filterQuery['type'] = $filterKey;
                                    $isFilterActive = $type === $filterKey;
                                }
                            @endphp

                            <a
                                href="{{ route('feed.index', $filterQuery) }}"
                                @class([
                                    'rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] transition-colors',
                                    $isFilterActive ? $activeTheme['chipActive'] : $activeTheme['chip'],
                                ])
                            >
                                {{ $filterLabel }}
                            </a>
                        @endforeach
                    </div>

                    <x-ui.alert type="info">
                        Feed note: private group posts only appear once you are an approved member of that group.
                    </x-ui.alert>

                    <div role="feed" aria-label="Pet feed" class="space-y-4">
                        @forelse ($posts as $post)
                            @include('partials.post-card', ['post' => $post, 'viewer' => $user])
                        @empty
                            <x-ui.empty-state
                                icon="🐾"
                                title="Follow some pet owners to see their posts here!"
                                description="Explore the community to find interesting pets and users."
                            >
                                <x-slot name="action">
                                    <x-ui.button href="{{ route('explore.index', ['tab' => 'users']) }}" variant="secondary" size="md">
                                        Explore pet owners
                                    </x-ui.button>
                                </x-slot>
                            </x-ui.empty-state>
                        @endforelse
                    </div>

                    @if ($posts->hasPages())
                        <x-ui.card class="border" :class="$activeTheme['panel']">
                            <x-ui.pagination :paginator="$posts" />
                        </x-ui.card>
                    @endif
                </div>

                <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
                    <x-ui.card class="border" :class="$activeTheme['panel']">
                        <x-slot name="header">
                            <x-ui.card-header title="Your Groups" subtitle="Communities you are active in">
                                <x-slot name="action">
                                    <x-ui.button href="{{ route('groups.index', ['privacy' => 'joined']) }}" variant="ghost" size="xs">Browse</x-ui.button>
                                </x-slot>
                            </x-ui.card-header>
                        </x-slot>

                        <div class="space-y-1 -mx-2">
                            @forelse ($yourGroups as $group)
                                @php
                                    $groupRouteKey = filled((string) ($group->slug ?? '')) ? $group->slug : $group->id;
                                @endphp

                                <x-ui.user-row
                                    :name="$group->name"
                                    :subtitle="\Illuminate\Support\Str::headline((string) ($group->privacy ?? 'public'))"
                                    :href="route('groups.show', $groupRouteKey)"
                                    class="px-2"
                                >
                                    <x-slot name="action">
                                        <span class="text-xs" @class([$activeTheme['kicker'], 'dark:text-zinc-300' => $theme !== 'high-contrast'])>
                                            {{ number_format((int) ($group->members_count ?? 0)) }}
                                        </span>
                                    </x-slot>
                                </x-ui.user-row>
                            @empty
                                <p class="px-2 text-sm" @class([$activeTheme['kicker'], 'dark:text-zinc-300' => $theme !== 'high-contrast'])>
                                    You have not joined any groups yet.
                                </p>
                            @endforelse
                        </div>

                        <div class="mt-4">
                            <x-ui.button href="{{ route('groups.create') }}" variant="primary" full>Create a Group</x-ui.button>
                        </div>
                    </x-ui.card>

                    <x-ui.card class="border" :class="$activeTheme['panel']">
                        <x-slot name="header">
                            <x-ui.card-header title="Suggested People" subtitle="Grow your pet network" />
                        </x-slot>

                        <div class="space-y-2">
                            @forelse ($suggestions as $suggestedUser)
                                <x-ui.user-row
                                    :name="$suggestedUser->name"
                                    :subtitle="'@'.$suggestedUser->username"
                                    :href="route('profile.show', $suggestedUser->username)"
                                >
                                    <x-slot name="avatar">
                                        <x-ui.avatar :src="$suggestedUser->avatar_url" :name="$suggestedUser->name" size="sm" />
                                    </x-slot>
                                </x-ui.user-row>
                            @empty
                                <p class="text-sm" @class([$activeTheme['kicker'], 'dark:text-zinc-300' => $theme !== 'high-contrast'])>
                                    Suggestions refresh as more members join.
                                </p>
                            @endforelse
                        </div>
                    </x-ui.card>

                    <x-ui.card class="border" :class="$activeTheme['panel']">
                        <x-slot name="header">
                            <x-ui.card-header title="Trending Hashtags" subtitle="Community momentum" />
                        </x-slot>

                        <div class="flex flex-wrap gap-2">
                            @forelse ($trending as $hashtag)
                                <a
                                    href="{{ route('hashtags.show', $hashtag->slug) }}"
                                    @class([
                                        'inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold',
                                        $activeTheme['chip'],
                                    ])
                                >
                                    #{{ $hashtag->name }}
                                </a>
                            @empty
                                <p class="text-sm" @class([$activeTheme['kicker'], 'dark:text-zinc-300' => $theme !== 'high-contrast'])>
                                    No hashtags trending yet.
                                </p>
                            @endforelse
                        </div>
                    </x-ui.card>

                    <x-ui.card class="border" :class="$activeTheme['panel']">
                        <x-slot name="header">
                            <x-ui.card-header title="Upcoming Events" subtitle="Meetups and care sessions" />
                        </x-slot>

                        <div class="space-y-3">
                            @forelse ($events as $event)
                                <a href="{{ route('events.show', $event) }}" class="block rounded-xl border border-whisker/40 px-3 py-2 transition hover:bg-cream/80 dark:border-zinc-700 dark:hover:bg-zinc-800">
                                    <p class="text-sm font-semibold">{{ $event->title }}</p>
                                    <p class="mt-0.5 text-xs" @class([$activeTheme['kicker'], 'dark:text-zinc-300' => $theme !== 'high-contrast'])>
                                        {{ optional($event->start_at)->format('M j, g:i A') }} · {{ $event->location_text ?: 'Online' }}
                                    </p>
                                </a>
                            @empty
                                <p class="text-sm" @class([$activeTheme['kicker'], 'dark:text-zinc-300' => $theme !== 'high-contrast'])>
                                    No upcoming events currently.
                                </p>
                            @endforelse
                        </div>

                        @if ($contest)
                            <div class="mt-4 rounded-xl border border-whisker/40 bg-amber-light/60 p-3 dark:border-zinc-700 dark:bg-zinc-800/80">
                                <p class="text-xs font-semibold uppercase tracking-[0.08em]" @class([$activeTheme['kicker'], 'dark:text-zinc-300' => $theme !== 'high-contrast'])>
                                    Active Contest
                                </p>
                                <p class="mt-1 text-sm font-semibold">{{ $contest->title }}</p>
                                <x-ui.button href="{{ route('contests.show', $contest->slug) }}" variant="ghost" size="xs" class="mt-2">
                                    View contest
                                </x-ui.button>
                            </div>
                        @endif
                    </x-ui.card>
                </aside>
            </div>
        </div>
    </section>
</x-app-layout>
