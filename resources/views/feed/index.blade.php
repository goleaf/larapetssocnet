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

 <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_17rem]" data-feed-surface="warm-editorial">
 <div class="space-y-4">
 @php
 $feedQuery = static fn (array $overrides = []): array => array_filter(array_merge([
 'source' => $source,
 'type' => $type,
 ], $overrides), static fn ($value): bool => filled($value));

 $sourceFilters = [
 ['value' => null, 'label' => __('feed.filters.all')],
 ['value' => 'people', 'label' => __('feed.filters.people')],
 ['value' => 'pets', 'label' => __('feed.filters.pets')],
 ];

 $typeFilters = [
 ['value' => null, 'label' => __('feed.filters.all_types')],
 ['value' => 'photo', 'label' => __('feed.filters.photos')],
 ['value' => 'video', 'label' => __('feed.filters.videos')],
 ['value' => 'text', 'label' => __('feed.filters.text')],
 ];
 @endphp

 <x-ui.card padding="base">
 <div class="grid gap-4 lg:grid-cols-[12rem_minmax(0,1fr)] lg:items-center">
 <div>
 <p class="shell-kicker">{{ __('feed.filters_title') }}</p>
 <h2 class="mt-1 text-lg font-bold font-display text-bark">{{ __('feed.filters_heading') }}</h2>
 </div>

 <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center lg:justify-end">
 <div class="flex flex-wrap gap-2" aria-label="{{ __('feed.filters_source_label') }}">
 @foreach ($sourceFilters as $filter)
 <a
 href="{{ route('feed.index', $feedQuery(['source' => $filter['value']])) }}"
 class="inline-flex min-h-10 items-center rounded-[var(--radius-soft)] border px-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw {{ $source === $filter['value'] ? 'border-paw-light bg-paw-light text-paw-dark' : 'border-whisker/40 bg-transparent text-fur hover:bg-cream hover:text-bark' }}"
 @if($source === $filter['value']) aria-current="true" @endif
 >
 {{ $filter['label'] }}
 </a>
 @endforeach
 </div>

 <div class="flex flex-wrap gap-2" aria-label="{{ __('feed.filters_type_label') }}">
 @foreach ($typeFilters as $filter)
 <a
 href="{{ route('feed.index', $feedQuery(['type' => $filter['value']])) }}"
 class="inline-flex min-h-10 items-center rounded-[var(--radius-soft)] border px-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw {{ $type === $filter['value'] ? 'border-paw-light bg-paw-light text-paw-dark' : 'border-whisker/40 bg-transparent text-fur hover:bg-cream hover:text-bark' }}"
 @if($type === $filter['value']) aria-current="true" @endif
 >
 {{ $filter['label'] }}
 </a>
 @endforeach
 </div>
 </div>
 </div>
 </x-ui.card>

 <x-ui.card padding="lg">
 <div class="mb-4 flex items-start gap-3 border-b border-whisker/30 pb-4">
 <x-avatar :src="$user->avatar_url" :name="$user->name" size="md"/>
 <div>
 <p class="text-sm font-semibold text-bark">{{ __('feed.create_post') }}</p>
 <p class="mt-1 text-xs leading-5 text-fur">{{ __('Share a pet moment, care question, or adoption update.') }}</p>
 </div>
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
 class="block w-full cursor-pointer text-sm text-fur file:mr-4 file:min-h-10 file:rounded-[var(--radius-soft)] file:border-0 file:bg-paw/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-paw hover:file:bg-paw/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
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

 <div class="mt-4 flex items-center justify-end border-t border-whisker/30 pt-4">
 <x-ui.button type="submit" variant="primary">{{ __('feed.post_button') }}</x-ui.button>
 </div>
 </form>
 </x-ui.card>

 <x-ui.card padding="base" role="status">
 <p class="flex items-start gap-2 text-sm leading-6 text-fur">
 <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-paw" aria-hidden="true"></span>
 <span>{{ __('feed.feed_note') }}</span>
 </p>
 </x-ui.card>

 <ul role="feed" aria-label="{{ __('feed.aria_feed') }}" class="space-y-4">
 @forelse ($posts as $post)
 <li aria-label="{{ __('Post by :name', ['name' => $post->author?->name ?? __('a community member')]) }}">
 <x-post-card :post="$post" />
 </li>
 @empty
 <li>
 <x-ui.empty-state :title="__('feed.empty_title')"
 :description="__('feed.empty_description')">
 <x-slot:action>
 <x-ui.button href="{{ route('explore.index', ['tab'=>'users']) }}" variant="secondary">{{ __('feed.empty_action') }}</x-ui.button>
 </x-slot:action>
 </x-ui.empty-state>
 </li>
 @endforelse
 </ul>

 @if ($posts->nextPageUrl())
 <x-ui.card padding="base">
 <a href="{{ $posts->nextPageUrl() }}" rel="next" class="inline-flex min-h-11 items-center text-sm font-medium text-paw hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
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
 <a href="{{ route('profile.show', ['user'=> $suggested]) }}" class="shrink-0 rounded-[var(--radius-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <x-ui.avatar :src="$suggested->avatar_url" :name="$suggested->name" size="md"/>
 </a>
 <div class="min-w-0">
 <a href="{{ route('profile.show', ['user'=> $suggested]) }}" class="block min-h-6 truncate text-sm font-semibold text-bark hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
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
 class="ui-list-item flex items-center justify-between px-3 py-2 group">
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
