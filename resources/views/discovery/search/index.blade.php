@section('title','Search')

<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header
 title="Find Pets, People, and Posts"
 description="Search across users, pets, groups, events, hashtags, and more."
 eyebrow="Global Search"
 icon="🔎"
 />
 </x-slot>

 <div class="space-y-5">
 <x-ui.panel padding="md">
 <form method="GET" action="{{ route('search.index') }}" class="grid gap-4 md:grid-cols-4">
 <x-ui.input class="md:col-span-3" id="q" name="q" label="Search Query" type="text" :value="$q" placeholder="Search users, pets, posts..." />

 <x-ui.select id="type" name="type" label="Result Type" :options="collect($types)->mapWithKeys(fn ($searchType) => [$searchType => ucfirst($searchType)])->all()" :value="$type" />

 <div class="md:col-span-4 flex justify-end">
 <x-ui.button type="submit">Search</x-ui.button>
 </div>
 </form>
 </x-ui.panel>

 @if($results->isEmpty())
 <x-ui.empty-state
 icon="🔎"
 title="No results found"
 description="Try a broader keyword or switch the result type filter."
 />
 @else
 <ul class="space-y-3" aria-label="Search results">
 @foreach($results as $row)
 @php
 $resultTitle = match ($type) {
 'posts' => 'Post',
 'hashtags' => '#'.$row->name,
 default => (string) ($row->name ?? $row->title ?? 'Result'),
 };

 $resultDescription = match ($type) {
 'users' => trim((string) ($row->headline ?? $row->bio ?? 'Community member')),
 'pets' => trim(collect([$row->species ?? null, $row->breed ?? null])->filter()->join(' · ')),
 'posts' => \Illuminate\Support\Str::limit(strip_tags((string) $row->body), 180),
 'groups', 'events' => \Illuminate\Support\Str::limit(strip_tags((string) $row->description), 150),
 default => number_format((int) ($row->posts_count ?? 0)).' posts',
 };

 if ($resultDescription === '') {
 $resultDescription = 'Open this result for more details.';
 }

 $resultMeta = match ($type) {
 'users' => '&#64;'.$row->username,
 'pets' => 'Pet profile',
 'posts' => optional($row->created_at)->diffForHumans() ?? 'Community post',
 'groups' => number_format((int) ($row->members_count ?? 0)).' members',
 'events' => optional($row->start_at)->format('M j, Y g:i A') ?? 'Event',
 default => 'Hashtag',
 };

 $resultHref = match ($type) {
 'users' => route('profile.show', ['user' => $row]),
 'pets' => route('pets.show', ['pet' => $row->slug ?? $row->getKey()]),
 'posts' => route('posts.show', ['post' => $row]),
 'groups' => route('groups.show', ['group' => filled((string) ($row->slug ?? '')) ? $row->slug : $row->getKey()]),
 'events' => route('events.show', ['event' => $row->getKey()]),
 default => route('hashtags.show', ['hashtag' => $row->slug ?? $row]),
 };

 $resultIcon = match ($type) {
 'users' => '👤',
 'pets' => '🐾',
 'posts' => '✎',
 'groups' => '👥',
 'events' => '📅',
 default => '#',
 };
 @endphp

 <li>
 <article class="shell-card group p-4 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-card-hover focus-within:shadow-card-hover" data-ui="search-result-card" aria-label="{{ __('Search result: :title', ['title' => $resultTitle]) }}">
 <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
 <div class="flex min-w-0 gap-3">
 <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-[var(--radius-card)] border border-whisker/40 bg-cream text-lg font-bold text-paw transition-colors group-hover:border-paw-light group-hover:bg-paw-light" aria-hidden="true">
 {{ $resultIcon }}
 </div>

 <div class="min-w-0">
 <div class="mb-1 flex flex-wrap items-center gap-2">
 <x-ui.badge size="sm">{{ \Illuminate\Support\Str::headline($type) }}</x-ui.badge>
 <span class="text-xs shell-text-muted">{!! $resultMeta !!}</span>
 </div>

 <h3 class="truncate text-base font-semibold ui-text">
 <a href="{{ $resultHref }}" class="hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 {{ $resultTitle }}
 </a>
 </h3>

 <p class="mt-1 line-clamp-2 text-sm leading-6 shell-text-muted">{{ $resultDescription }}</p>
 </div>
 </div>

 <x-ui.button :href="$resultHref" variant="ghost" size="sm" class="min-h-11 sm:shrink-0">
 Open
 </x-ui.button>
 </div>
 </article>
 </li>
 @endforeach
 </ul>

 <div class="mt-4">
 {{ $results->links() }}
 </div>
 @endif
 </div>
</x-app-layout>
