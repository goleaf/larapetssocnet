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

        $typeFilters = [
            'all' => 'All posts',
            'text' => 'Text',
            'photo' => 'Photos',
            'video' => 'Video',
        ];
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <x-ui.page-header title="Community Feed" subtitle="See what's happening in the community." class="mb-0" />

            <div class="flex items-center gap-2">
                <x-ui.button href="{{ route('saved.index') }}" variant="ghost" size="sm">Saved</x-ui.button>
                <x-ui.button href="{{ route('explore.index') }}" variant="ghost" size="sm">Explore</x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="mt-4 flex flex-col gap-5 max-w-4xl mx-auto">
        <div class="space-y-4">
            <x-ui.card class="border">
                <x-slot name="header">
                    <x-ui.card-header title="Create a post" subtitle="Share something about your pet today.">
                        <x-slot name="action">
                            <x-ui.avatar :src="auth()->user()?->avatar_url" :name="auth()->user()?->name" size="md" />
                        </x-slot>
                    </x-ui.card-header>
                </x-slot>

                <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <x-ui.textarea name="body" label="Body" rows="3" placeholder="Share an update about your pet..."
                        :error="$errors->first('body')">{{ old('body') }}</x-ui.textarea>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <x-ui.select name="pet_id" label="Pet" :error="$errors->first('pet_id')">
                            <option value="">No pet tag</option>
                            @foreach (auth()->user()->pets as $pet)
                                <option value="{{ $pet->id }}" @selected((string) old('pet_id') === (string) $pet->id)>
                                    {{ $pet->name }}
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.file-upload name="media" label="Media" multiple
                            accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime"
                            :error="$postMediaError" hint="Upload up to 4 files." />
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

                    <a href="{{ route('feed.index', $filterQuery) }}" @class([
                        'rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] transition-colors',
                        $isFilterActive ? 'bg-paw text-white border-paw' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50',
                    ])>
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
                    <x-ui.empty-state icon="🐾" title="Follow some pet owners to see their posts here!"
                        description="Explore the community to find interesting pets and users.">
                        <x-slot name="action">
                            <x-ui.button href="{{ route('explore.index', ['tab' => 'users']) }}" variant="secondary"
                                size="md">
                                Explore pet owners
                            </x-ui.button>
                        </x-slot>
                    </x-ui.empty-state>
                @endforelse
            </div>

            @if ($posts->hasPages())
                <x-ui.card class="border">
                    <x-ui.pagination :paginator="$posts" />
                </x-ui.card>
            @endif
        </div>

        <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
            <x-ui.card class="border">
                <x-slot name="header">
                    <x-ui.card-header title="Your Groups" subtitle="Communities you are active in">
                        <x-slot name="action">
                            <x-ui.button href="{{ route('groups.index', ['privacy' => 'joined']) }}" variant="ghost"
                                size="xs">Browse</x-ui.button>
                        </x-slot>
                    </x-ui.card-header>
                </x-slot>

                <div class="space-y-1 -mx-2">
                    @forelse ($yourGroups as $group)
                        @php
                            $groupRouteKey = filled((string) ($group->slug ?? '')) ? $group->slug : $group->id;
                        @endphp

                        <x-ui.user-row :name="$group->name" :subtitle="\Illuminate\Support\Str::headline((string) ($group->privacy ?? 'public'))" :href="route('groups.show', $groupRouteKey)" class="px-2">
                            <x-slot name="action">
                                <span class="text-xs text-fur">
                                    {{ number_format((int) ($group->members_count ?? 0)) }}
                                </span>
                            </x-slot>
                        </x-ui.user-row>
                    @empty
                        <p class="px-2 text-sm text-fur">
                            You have not joined any groups yet.
                        </p>
                    @endforelse
                </div>

                <div class="mt-4">
                    <x-ui.button href="{{ route('groups.create') }}" variant="primary" full>Create a Group</x-ui.button>
                </div>
            </x-ui.card>

            <x-ui.card class="border">
                <x-slot name="header">
                    <x-ui.card-header title="Suggested People" subtitle="Grow your pet network" />
                </x-slot>

                <div class="space-y-2">
                    @forelse ($suggestions as $suggestedUser)
                        <x-ui.user-row :name="$suggestedUser->name" :subtitle="'@' . $suggestedUser->username"
                            :href="route('profile.show', $suggestedUser->username)">
                            <x-slot name="avatar">
                                <x-ui.avatar :src="$suggestedUser->avatar_url" :name="$suggestedUser->name" size="sm" />
                            </x-slot>
                        </x-ui.user-row>
                    @empty
                        <p class="text-sm text-fur">
                            Suggestions refresh as more members join.
                        </p>
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card class="border">
                <x-slot name="header">
                    <x-ui.card-header title="Trending Hashtags" subtitle="Community momentum" />
                </x-slot>

                <div class="flex flex-wrap gap-2">
                    @forelse ($trending as $hashtag)
                        <a href="{{ route('hashtags.show', $hashtag->slug) }}"
                            class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            #{{ $hashtag->name }}
                        </a>
                    @empty
                        <p class="text-sm text-fur">
                            No hashtags trending yet.
                        </p>
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card class="border">
                <x-slot name="header">
                    <x-ui.card-header title="Upcoming Events" subtitle="Meetups and care sessions" />
                </x-slot>

                <div class="space-y-3">
                    @forelse ($events as $event)
                        <a href="{{ route('events.show', $event) }}"
                            class="block rounded-xl border border-whisker/40 px-3 py-2 transition hover:bg-cream/80 dark:border-zinc-700 dark:hover:bg-zinc-800">
                            <p class="text-sm font-semibold">{{ $event->title }}</p>
                            <p class="mt-0.5 text-xs text-fur">
                                {{ optional($event->start_at)->format('M j, g:i A') }} ·
                                {{ $event->location_text ?: 'Online' }}
                            </p>
                        </a>
                    @empty
                        <p class="text-sm text-fur">
                            No upcoming events currently.
                        </p>
                    @endforelse
                </div>

                @if ($contest)
                    <div
                        class="mt-4 rounded-xl border border-whisker/40 bg-amber-light/60 p-3 dark:border-zinc-700 dark:bg-zinc-800/80">
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-fur">
                            Active Contest
                        </p>
                        <p class="mt-1 text-sm font-semibold">{{ $contest->title }}</p>
                        <x-ui.button href="{{ route('contests.show', $contest->slug) }}" variant="ghost" size="xs"
                            class="mt-2">
                            View contest
                        </x-ui.button>
                    </div>
                @endif
            </x-ui.card>
        </aside>
    </div>
    </div>
</x-app-layout>