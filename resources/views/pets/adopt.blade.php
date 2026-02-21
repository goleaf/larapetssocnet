<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Adopt a Pet
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('pets.adopt') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <x-input-label for="q" value="Search" />
                        <x-text-input id="q" name="q" class="mt-1 block w-full" :value="$filters['q']" placeholder="Name or breed" />
                    </div>

                    <div>
                        <x-input-label for="species" value="Species" />
                        <x-text-input id="species" name="species" class="mt-1 block w-full" :value="$filters['species']" />
                    </div>

                    <div>
                        <x-input-label for="sex" value="Sex" />
                        <select id="sex" name="sex" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Any</option>
                            <option value="male" @selected($filters['sex'] === 'male')>Male</option>
                            <option value="female" @selected($filters['sex'] === 'female')>Female</option>
                            <option value="unknown" @selected($filters['sex'] === 'unknown')>Unknown</option>
                        </select>
                    </div>

                    <div>
                        <x-input-label for="sort" value="Sort" />
                        <select id="sort" name="sort" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="newest" @selected($filters['sort'] === 'newest')>Newest</option>
                            <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest</option>
                            <option value="name_asc" @selected($filters['sort'] === 'name_asc')>Name A-Z</option>
                            <option value="name_desc" @selected($filters['sort'] === 'name_desc')>Name Z-A</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-5 flex items-center justify-between">
                        <p class="text-sm text-gray-600">Showing pets currently marked for adoption.</p>

                        <div class="flex items-center gap-2">
                            <x-primary-button>Apply filters</x-primary-button>
                            <a href="{{ route('pets.adopt') }}" class="text-sm text-gray-600 hover:text-gray-900">Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            @if($pets->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
                    No adoptable pets match your filters.
                </div>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($pets as $pet)
                        @php
                            $petSlug = $pet->slug ?? $pet->getKey();
                        @endphp
                        <article class="rounded-lg border border-emerald-200 bg-white p-5 shadow-sm">
                            <div class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">For adoption</div>
                            <h3 class="mt-3 text-lg font-semibold text-gray-900">{{ $pet->name ?? 'Unnamed pet' }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ $pet->species ?? 'Unknown species' }} @if(!empty($pet->breed)) • {{ $pet->breed }} @endif</p>
                            <p class="mt-2 text-sm text-gray-600">{{ \Illuminate\Support\Str::limit((string) ($pet->bio ?? 'No bio yet.'), 130) }}</p>

                            <a href="{{ route('pets.show', $petSlug) }}" class="mt-4 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-800">View profile</a>
                        </article>
                    @endforeach
                </div>

                <div>
                    {{ $pets->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
