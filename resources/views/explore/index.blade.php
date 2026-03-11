@section('title','Explore')

<x-app-layout>
 <x-slot name="header">
 <div class="flex flex-wrap items-center justify-between gap-3">
 <x-ui.page-header title="Explore Public Posts"subtitle="Discover"
 description="Find photos, videos, trending topics, and new creators."/>

 @auth
 <x-ui.button href="{{ route('posts.create') }}"variant="primary"
 icon="<path stroke-linecap='round'stroke-linejoin='round'd='M12 4.5v15m7.5-7.5h-15'/>">New
 Post</x-ui.button>
 @endauth
 </div>
 </x-slot>

 <div class="space-y-4">
 <div class="bg-transparent border-none shadow-none">
 <form method="GET"action="{{ route('explore.index') }}"class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
 <x-ui.input type="text"name="q"value="{{ $search }}"
 placeholder="Search posts, users, hashtags, or location">
 <x-slot name="prefix">
 <svg class="w-5 h-5 text-fur"fill="none"viewBox="0 0 24 24"stroke-width="1.5"
 stroke="currentColor">
 <path stroke-linecap="round"stroke-linejoin="round"
 d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
 </svg>
 </x-slot>
 </x-ui.input>

 <x-ui.select name="type"class="sm:min-w-[10rem]">
 <option value="all"@selected($type ==='all')>All Posts</option>
 <option value="photos"@selected($type ==='photos')>Photos</option>
 <option value="videos"@selected($type ==='videos')>Videos</option>
 <option value="trending"@selected($type ==='trending')>Trending (48h)</option>
 </x-ui.select>

 <x-ui.button type="submit"variant="primary">Apply</x-ui.button>
 </form>

 <div class="mt-4 flex flex-wrap gap-2">
 @foreach (['all'=>'All','photos'=>'Photos','videos'=>'Videos','trending'=>'Trending'] as $option => $label)
 <x-ui.badge :variant="$type === $option ?'primary':'default'"pill>
 <a
 href="{{ route('explore.index', array_merge(request()->except('page','type'), ['type'=> $option])) }}">
 {{ $label }}
 </a>
 </x-ui.badge>
 @endforeach
 </div>
 </div>

 @forelse ($posts as $post)
 @include('partials.post-card', ['post'=> $post])
 @empty
 <x-ui.empty-state icon="🔎"title="No public posts found"
 description="Try a different search term, media type, or check back soon for new activity."/>
 @endforelse

 @if($posts->hasPages())
 <x-ui.card>
 <x-ui.pagination :paginator="$posts"/>
 </x-ui.card>
 @endif
 </div>
</x-app-layout>