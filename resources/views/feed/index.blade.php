@section('title', __('en.feed_petsocial'))

<x-app-layout>
    @php
        $feedThemes = [
            'accessible-soft' => 'Accessible Soft',
            'high-contrast' => 'High Contrast',
            'minimalist-soothe' => 'Minimalist Soothe',
        ];
        $requestedTheme = request()->query('theme');
        $activeFeedTheme = is_string($requestedTheme) && array_key_exists($requestedTheme, $feedThemes)
            ? $requestedTheme
            : 'accessible-soft';
        $activeFeedThemeLabel = $feedThemes[$activeFeedTheme];
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
    @endphp

    <x-slot name="header">
        <x-ui.page-header title="Community Feed" :subtitle="$activeFeedThemeLabel" icon="📰">
            <x-slot:action>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button href="{{ route('saved.index') }}" variant="ghost" size="sm">{{ __('en.saved_2') }}</x-ui.button>
                    <x-ui.button href="{{ route('explore.index') }}" variant="ghost" size="sm">{{ __('en.explore') }}</x-ui.button>
                </div>
            </x-slot:action>
        </x-ui.page-header>
    </x-slot>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_17rem]" data-feed-theme="{{ $activeFeedTheme }}">
        <div class="space-y-4">
            <x-ui.card padding="lg">
                <div class="mb-4 flex items-center gap-3 border-b border-whisker/30 pb-4">
                    <x-avatar :src="auth()->user()?->avatar_url" :name="auth()->user()?->name" size="md"  />
                    <p class="text-sm font-semibold text-bark">{{ __('en.create_a_post') }}</p>
                </div>

                <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <x-ui.textarea id="feed-post-body" name="body" rows="3"
                            :placeholder="__('en.share_an_update_about_your_pet')"
                            class="!border-0 !bg-transparent !p-0 !shadow-none focus:!ring-0 text-lg placeholder:text-fur">{{ old('body') }}</x-ui.textarea>
                        @error('body')
                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 pt-2">
                        <div>
                            <x-ui.label for="feed-post-pet-id"
                                class="!mb-1 text-xs uppercase tracking-wide">{{ __('en.pet') }}</x-ui.label>
                            <x-ui.select id="feed-post-pet-id" name="pet_id" :options="collect(['' => __('en.no_pet_tag')])->merge(auth()->user()->pets->pluck('name', 'id'))->all()" :value="old('pet_id')"  />
                            @error('pet_id')
                                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-ui.label for="feed-post-photos"
                                class="!mb-1 text-xs uppercase tracking-wide">{{ __('en.media') }}</x-ui.label>
                            <input id="feed-post-photos" type="file" name="media[]" multiple
                                accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime"
                                class="block w-full text-sm text-fur file:mr-4 file:rounded-full file:border-0 file:bg-paw/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-paw hover:file:bg-paw/20 cursor-pointer">
                            @error('media')
                                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                            @error('media.*')
                                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end pt-4 mt-4 border-t border-whisker/30">
                        <x-ui.button type="submit" variant="primary">{{ __('en.post') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card padding="base" class="bg-warm-white bg-opacity-50">
                <p class="text-xs text-fur flex items-center gap-2">
                    <span class="text-base">ℹ️</span> {{ __('en.feed_note_private_group_posts_only_appear_once_you_are_an_approved_member_of_tha') }}
                </p>
            </x-ui.card>

            <div role="feed" aria-label="Pet feed" class="space-y-4">
                @forelse ($posts as $post)
                    @include('partials.post-card', ['post' => $post, 'viewer' => $user])
                @empty
                    <x-ui.empty-state :title="__('en.follow_some_pet_owners_to_see_their_posts_here')"
                        :description="__('en.your_feed_is_lonely_right_now')">
                        <x-slot:action>
                            <x-ui.button href="{{ route('explore.index', ['tab' => 'users']) }}" variant="secondary">{{ __('en.explore_pet_owners') }}</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty-state>
                @endforelse
            </div>

            @if ($posts->hasPages())
                <x-ui.card padding="base">
                    {{ $posts->onEachSide(1)->links() }}
                </x-ui.card>
            @endif
        </div>

        <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
            <x-ui.card padding="base">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-xs font-bold uppercase tracking-wider text-fur">{{ __('en.your_groups') }}</p>
                    <a href="{{ route('groups.index', ['privacy' => 'joined']) }}"
                        class="text-xs font-semibold text-paw hover:underline">{{ __('en.browse') }}</a>
                </div>

                <div class="space-y-2.5">
                    @forelse ($yourGroups as $group)
                        @php
                            $groupRouteKey = filled((string) ($group->slug ?? '')) ? $group->slug : $group->id;
                        @endphp

                        <a href="{{ route('groups.show', $groupRouteKey) }}"
                            class="flex items-center justify-between rounded-xl border border-whisker/30 px-3 py-2 hover:bg-warm-white transition-colors group">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-bark group-hover:text-paw transition-colors">
                                    {{ $group->name }}</p>
                                <p class="truncate text-xs text-fur">
                                    {{ \Illuminate\Support\Str::headline((string) ($group->privacy ?? 'public')) }}</p>
                            </div>
                            <span
                                class="text-xs font-medium text-fur bg-whisker/20 px-2 py-0.5 rounded-full">{{ number_format((int) ($group->members_count ?? 0)) }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-fur">{{ __('en.you_have_not_joined_any_groups_yet') }}</p>
                    @endforelse
                </div>

                @auth
                    <x-ui.button href="{{ route('groups.create') }}" variant="primary"
                        class="mt-4 w-full justify-center">{{ __('en.create_a_group') }}</x-ui.button>
                @endauth
            </x-ui.card>
        </aside>
    </div>
</x-app-layout>
