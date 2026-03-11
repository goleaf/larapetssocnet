@section('title','Search')

<x-app-layout>
 <x-slot name="header">
 <div>
 <p class="shell-kicker">Global Search</p>
 <h1 class="shell-title text-2xl">Find Pets, People, and Posts</h1>
 <p class="mt-1 text-sm shell-text-muted">Search across users, pets, groups, events, hashtags, and more.</p>
 </div>
 </x-slot>

 <div class="space-y-5">
 <section class="shell-panel p-4 sm:p-5">
 <form method="GET" action="{{ route('search.index') }}" class="grid gap-4 md:grid-cols-4">
 <div class="md:col-span-3">
 <x-input-label for="q" value="Search Query" />
 <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$q" placeholder="Search users, pets, posts..." />
 </div>

 <div>
 <x-input-label for="type" value="Result Type" />
 <select id="type" name="type" class="form-select mt-1 block w-full">
 @foreach($types as $searchType)
 <option value="{{ $searchType }}"@selected($type === $searchType)>{{ ucfirst($searchType) }}</option>
 @endforeach
 </select>
 </div>

 <div class="md:col-span-4 flex justify-end">
 <x-primary-button>Search</x-primary-button>
 </div>
 </form>
 </section>

 <section class="shell-card p-4 sm:p-5">
 @if($results->isEmpty())
 <x-empty-state
 icon="🔎"
 title="No results found"
 description="Try a broader keyword or switch the result type filter."
  />
 @else
 <div class="space-y-3">
 @foreach($results as $row)
 <article class="hover-lift rounded-xl border p-4" style="border-color: var(--ui-border);">
 @if($type ==='users')
 <div class="font-semibold" style="color: var(--ui-text);">{{ $row->name }}</div>
 <div class="text-sm shell-text-muted">&#64;{{ $row->username }}</div>
 @elseif($type ==='pets')
 <div class="font-semibold" style="color: var(--ui-text);">{{ $row->name }}</div>
 <div class="text-sm shell-text-muted">{{ $row->species }} @if($row->breed) · {{ $row->breed }} @endif</div>
 @elseif($type ==='posts')
 <div class="text-sm" style="color: var(--ui-text);">{{ \Illuminate\Support\Str::limit(strip_tags((string) $row->body), 180) }}</div>
 @elseif($type ==='groups')
 <div class="font-semibold" style="color: var(--ui-text);">{{ $row->name }}</div>
 <div class="text-sm shell-text-muted">{{ \Illuminate\Support\Str::limit((string) $row->description, 150) }}</div>
 @elseif($type ==='events')
 <div class="font-semibold" style="color: var(--ui-text);">{{ $row->title }}</div>
 <div class="text-sm shell-text-muted">{{ \Illuminate\Support\Str::limit((string) $row->description, 150) }}</div>
 @else
 <div class="font-semibold" style="color: var(--ui-text);">#{{ $row->name }}</div>
 <div class="text-sm shell-text-muted">{{ $row->posts_count ?? 0 }} posts</div>
 @endif
 </article>
 @endforeach
 </div>

 <div class="mt-4 shell-card-muted p-3">
 {{ $results->links() }}
 </div>
 @endif
 </section>
 </div>
</x-app-layout>
