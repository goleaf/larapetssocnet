@section('title', __('feed.page_title'))

<x-app-layout>
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

 <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4"
 x-data="{ status: '{{ old('status', 'published') }}' }">
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
 <x-ui.label class="!mb-1 text-xs uppercase tracking-wide">Visibility</x-ui.label>
 <x-visibility-selector :selected="old('visibility', 'public')" :showWarn="false" />
 @error('visibility')
 <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
 @enderror
 </div>

 <div>
 <x-ui.label for="feed-post-status"
 class="!mb-1 text-xs uppercase tracking-wide">Status</x-ui.label>
 <x-ui.select id="feed-post-status" name="status" x-model="status">
 <option value="published">Publish now</option>
 <option value="scheduled">Schedule</option>
 <option value="draft">Draft</option>
 </x-ui.select>
 @error('status')
 <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
 @enderror
 </div>

 <div>
 <x-ui.label for="feed-post-pet-id"
 class="!mb-1 text-xs uppercase tracking-wide">{{ __('feed.pet_label') }}</x-ui.label>
 <x-ui.select id="feed-post-pet-id" name="pet_id" :options="collect(['' => __('feed.no_pet_tag')])->merge(collect($ownedPets ?? [])->pluck('name','id'))->all()" :value="old('pet_id')"/>
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

 <div x-show="status === 'scheduled'" x-cloak>
 <x-ui.label for="feed-post-published-at"
 class="!mb-1 text-xs uppercase tracking-wide">Publish at</x-ui.label>
 <x-ui.input type="datetime-local" id="feed-post-published-at" name="published_at" :value="old('published_at')" />
 @error('published_at')
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
 @if (collect($suggestions ?? [])->isNotEmpty())
 <x-ui.card padding="base">
 <div class="mb-4 flex items-center justify-between">
 <p class="text-xs font-bold uppercase tracking-wider text-fur">Who to Follow</p>
 <a href="{{ route('search.index', ['type'=>'users']) }}"
 class="text-xs font-semibold text-paw hover:underline">See all</a>
 </div>

 <div class="space-y-3">
 @foreach (collect($suggestions ?? []) as $suggested)
 <div class="flex items-center justify-between gap-3">
 <div class="min-w-0 flex items-center gap-3">
 <a href="{{ route('profile.show', ['user'=> $suggested]) }}" class="shrink-0">
 <x-ui.avatar :src="$suggested->avatar_url" :name="$suggested->name" size="md"/>
 </a>
 <div class="min-w-0">
 <a href="{{ route('profile.show', ['user'=> $suggested]) }}" class="block truncate text-sm font-semibold text-bark hover:text-paw">
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

 <x-ui.card padding="base">
 <div class="mb-4 flex items-center justify-between">
 <p class="text-xs font-bold uppercase tracking-wider text-fur">{{ __('feed.groups_title') }}</p>
 <a href="{{ route('groups.index', ['privacy'=>'joined']) }}"
 class="text-xs font-semibold text-paw hover:underline">{{ __('feed.browse') }}</a>
 </div>

 <div class="space-y-2.5">
 @forelse (collect($yourGroups ?? []) as $group)
 <a href="{{ route('groups.show', filled((string) ($group->slug ?? '')) ? $group->slug : $group->id) }}"
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
