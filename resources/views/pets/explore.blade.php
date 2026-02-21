<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Explore Pets
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('pets.adopt') }}" class="text-sm text-emerald-600 hover:text-emerald-800">Browse adoption</a>
                <a href="{{ route('pets.create') }}" class="text-sm text-indigo-600 hover:text-indigo-800">Create pet profile</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('pets.explore') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <div class="lg:col-span-2">
                        <x-input-label for="q" value="Search" />
                        <x-text-input id="q" name="q" class="mt-1 block w-full" :value="$filters['q']" placeholder="Name, breed, species" />
                    </div>

                    <div>
                        <x-input-label for="species" value="Species" />
                        <x-text-input id="species" name="species" class="mt-1 block w-full" :value="$filters['species']" />
                    </div>

                    <div>
                        <x-input-label for="breed" value="Breed" />
                        <x-text-input id="breed" name="breed" class="mt-1 block w-full" :value="$filters['breed']" />
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
                            <option value="weight_desc" @selected($filters['sort'] === 'weight_desc')>Heaviest</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-6 flex items-center justify-between">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_adoptable" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked($filters['is_adoptable'])>
                            Show only pets for adoption
                        </label>

                        <div class="flex items-center gap-2">
                            <x-primary-button>Apply filters</x-primary-button>
                            <a href="{{ route('pets.explore') }}" class="text-sm text-gray-600 hover:text-gray-900">Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            @if($pets->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
                    No pets match your filters.
                </div>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($pets as $pet)
                        @php
                            $petSlug = $pet->slug ?? $pet->getKey();
                        @endphp
                        <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $pet->name ?? 'Unnamed pet' }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ $pet->species ?? 'Unknown species' }} @if(!empty($pet->breed)) • {{ $pet->breed }} @endif</p>
                            <p class="mt-2 text-sm text-gray-600">{{ \Illuminate\Support\Str::limit((string) ($pet->bio ?? 'No bio yet.'), 130) }}</p>

                            <div class="mt-3 flex flex-wrap gap-2">
                                @if(!empty($pet->is_adoptable) || !empty($pet->is_for_adoption))
                                    <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">Adoption</span>
                                @endif
                                @if(!empty($pet->sex) || !empty($pet->gender))
                                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ ucfirst($pet->sex ?? $pet->gender) }}</span>
                                @endif
                            </div>

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
