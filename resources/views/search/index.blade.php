<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Search
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('search.index') }}" class="grid gap-4 md:grid-cols-4">
                    <div class="md:col-span-3">
                        <x-input-label for="q" value="Query" />
                        <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$q" placeholder="Search users, pets, posts..." />
                    </div>

                    <div>
                        <x-input-label for="type" value="Type" />
                        <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($types as $searchType)
                                <option value="{{ $searchType }}" @selected($type === $searchType)>{{ ucfirst($searchType) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-4 flex justify-end">
                        <x-primary-button>Search</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                @if($results->isEmpty())
                    <p class="text-sm text-gray-500">No results found.</p>
                @else
                    <div class="space-y-3">
                        @foreach($results as $row)
                            <article class="rounded-lg border border-gray-200 p-4">
                                @if($type === 'users')
                                    <div class="font-semibold">{{ $row->name }}</div>
                                    <div class="text-sm text-gray-500">@{{ $row->username }}</div>
                                @elseif($type === 'pets')
                                    <div class="font-semibold">{{ $row->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $row->species }} @if($row->breed) • {{ $row->breed }} @endif</div>
                                @elseif($type === 'posts')
                                    <div class="text-sm text-gray-700">{{ \Illuminate\Support\Str::limit(strip_tags((string) $row->body), 180) }}</div>
                                @elseif($type === 'groups')
                                    <div class="font-semibold">{{ $row->name }}</div>
                                    <div class="text-sm text-gray-500">{{ \Illuminate\Support\Str::limit((string) $row->description, 150) }}</div>
                                @elseif($type === 'events')
                                    <div class="font-semibold">{{ $row->title }}</div>
                                    <div class="text-sm text-gray-500">{{ \Illuminate\Support\Str::limit((string) $row->description, 150) }}</div>
                                @else
                                    <div class="font-semibold">#{{ $row->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $row->posts_count ?? 0 }} posts</div>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        {{ $results->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
