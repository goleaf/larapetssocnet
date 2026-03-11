@section('title', __('feed.page_title'))

<x-app-layout>
 @php
 $feedThemes = [
 'accessible-soft' => __('feed.themes.accessible_soft'),
 'high-contrast' => __('feed.themes.high_contrast'),
 'minimalist-soothe' => __('feed.themes.minimalist_soothe'),
 ];
 $requestedTheme = request()->query('theme');
 $activeFeedTheme = is_string($requestedTheme) && array_key_exists($requestedTheme, $feedThemes)
 ? $requestedTheme
 :'accessible-soft';
 $activeFeedThemeLabel = $feedThemes[$activeFeedTheme];
 $yourGroups = $yourGroups ?? collect();
 @endphp

 <x-slot name="header">
 <x-ui.page-header :title="__('feed.header_title')" :subtitle="$activeFeedThemeLabel" icon="📰">
 <x-slot:action>
 <div class="flex flex-wrap items-center gap-2">
 <x-ui.button href="{{ route('saved.index') }}" variant="ghost" size="sm">{{ __('feed.saved') }}</x-ui.button>
 <x-ui.button href="{{ route('explore.index') }}" variant="ghost" size="sm">{{ __('feed.explore') }}</x-ui.button>
 </div>
 </x-slot:action>
 </x-ui.page-header>
 </x-slot>

 <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_17rem]" data-feed-theme="{{ $activeFeedTheme }}">
 <div class="space-y-4">
 <x-ui.card padding="lg">
 <div class="mb-4 flex items-center gap-3 border-b border-whisker/30 pb-4">
 <x-avatar :src="$user->avatar_url" :name="$user->name" size="md"/>
 <p class="text-sm font-semibold text-bark">{{ __('feed.create_post') }}</p>
 </div>

 <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
 @csrf
 <div>
 <x-ui.textarea id="feed-post-body" name="body" rows="3"
 placeholder="{{ __('feed.placeholder_share_update') }}"
 class="!border-0 !bg-transparent !p-0 !shadow-none focus:!ring-0 text-lg placeholder:text-fur">{{ old('body') }}</x-ui.textarea>
 @error('body')
 <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
 @enderror
 </div>

 <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 pt-2">
 <div>
 <x-ui.label for="feed-post-pet-id"
 class="!mb-1 text-xs uppercase tracking-wide">{{ __('feed.pet_label') }}</x-ui.label>
 <x-ui.select id="feed-post-pet-id" name="pet_id" :options="collect(['' => __('feed.no_pet_tag')])->merge($user->pets->pluck('name','id'))->all()" :value="old('pet_id')"/>
 @error('pet_id')
 <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
 @enderror
 </div>

 <div>
 <x-ui.label for="feed-post-photos"
 class="!mb-1 text-xs uppercase tracking-wide">{{ __('feed.media_label') }}</x-ui.label>
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
 <x-ui.button type="submit" variant="primary">{{ __('feed.post_button') }}</x-ui.button>
 </div>
 </form>
 </x-ui.card>

 <x-ui.card padding="base" class="bg-warm-white bg-opacity-50">
 <p class="text-xs text-fur flex items-center gap-2">
 <span class="text-base">ℹ️</span> {{ __('feed.feed_note') }}
 </p>
 </x-ui.card>

 <div role="feed" aria-label="{{ __('feed.aria_feed') }}" class="space-y-4">
 @forelse ($posts as $post)
 <x-post-card :post="$post" />
 @empty
 <x-ui.empty-state :title="__('feed.empty_title')"
 :description="__('feed.empty_description')">
 <x-slot:action>
 <x-ui.button href="{{ route('explore.index', ['tab'=>'users']) }}" variant="secondary">{{ __('feed.empty_action') }}</x-ui.button>
 </x-slot:action>
 </x-ui.empty-state>
 @endforelse
 </div>

 @if ($posts->nextPageUrl())
 <x-ui.card padding="base">
 <a href="{{ $posts->nextPageUrl() }}" rel="next" class="inline-flex text-sm font-medium text-paw hover:underline">
 {{ __('feed.next_cursor') }}
 </a>
 </x-ui.card>
 @endif
 </div>

 <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
 <x-ui.card padding="base">
 <div class="mb-4 flex items-center justify-between">
 <p class="text-xs font-bold uppercase tracking-wider text-fur">{{ __('feed.groups_title') }}</p>
 <a href="{{ route('groups.index', ['privacy'=>'joined']) }}"
 class="text-xs font-semibold text-paw hover:underline">{{ __('feed.browse') }}</a>
 </div>

 <div class="space-y-2.5">
 @forelse ($yourGroups as $group)
 @php
 $groupRouteKey = filled((string) ($group->slug ??'')) ? $group->slug : $group->id;
 @endphp

 <a href="{{ route('groups.show', $groupRouteKey) }}"
 class="flex items-center justify-between rounded-xl border border-whisker/30 px-3 py-2 hover:bg-warm-white transition-colors group">
 <div class="min-w-0">
 <p class="truncate text-sm font-semibold text-bark group-hover:text-paw transition-colors">
 {{ $group->name }}</p>
 <p class="truncate text-xs text-fur">
 {{ \Illuminate\Support\Str::headline((string) ($group->privacy ??'public')) }}</p>
 </div>
 <span
 class="text-xs font-medium text-fur bg-whisker/20 px-2 py-0.5 rounded-full">{{ number_format((int) ($group->members_count ?? 0)) }}</span>
 </a>
 @empty
 <p class="text-sm text-fur">{{ __('feed.no_groups') }}</p>
 @endforelse
 </div>

 @auth
 <x-ui.button href="{{ route('groups.create') }}" variant="primary"
 class="mt-4 w-full justify-center">{{ __('feed.create_group') }}</x-ui.button>
 @endauth
 </x-ui.card>
 </aside>
 </div>
</x-app-layout>
