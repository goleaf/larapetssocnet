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

 <x-ui.card>
 @if($results->isEmpty())
 <x-ui.empty-state
 icon="🔎"
 title="No results found"
 description="Try a broader keyword or switch the result type filter."
 />
 @else
 <div class="space-y-3">
 @foreach($results as $row)
 <article class="ui-surface hover-lift p-4">
 @if($type === 'users')
 <div class="font-semibold text-bark">{{ $row->name }}</div>
 <div class="text-sm text-fur">&#64;{{ $row->username }}</div>
 @elseif($type === 'pets')
 <div class="font-semibold text-bark">{{ $row->name }}</div>
 <div class="text-sm text-fur">{{ $row->species }} @if($row->breed) · {{ $row->breed }} @endif</div>
 @elseif($type === 'posts')
 <div class="text-sm text-bark">{{ \Illuminate\Support\Str::limit(strip_tags((string) $row->body), 180) }}</div>
 @elseif($type === 'groups')
 <div class="font-semibold text-bark">{{ $row->name }}</div>
 <div class="text-sm text-fur">{{ \Illuminate\Support\Str::limit((string) $row->description, 150) }}</div>
 @elseif($type === 'events')
 <div class="font-semibold text-bark">{{ $row->title }}</div>
 <div class="text-sm text-fur">{{ \Illuminate\Support\Str::limit((string) $row->description, 150) }}</div>
 @else
 <div class="font-semibold text-bark">#{{ $row->name }}</div>
 <div class="text-sm text-fur">{{ $row->posts_count ?? 0 }} posts</div>
 @endif
 </article>
 @endforeach
 </div>

 <div class="mt-4">
 {{ $results->links() }}
 </div>
 @endif
 </x-ui.card>
 </div>
</x-app-layout>
