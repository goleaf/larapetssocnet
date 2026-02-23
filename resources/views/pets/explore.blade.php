<x-app-layout>
    @php
        $speciesOptions = collect(\App\Models\Pet::SPECIES)
            ->map(static fn (string $species): array => [
                'value' => $species,
                'label' => \Illuminate\Support\Str::headline($species),
            ])
            ->prepend([
                'value' => '',
                'label' => 'All species',
            ])
            ->values()
            ->all();

        $sexOptions = [
            ['value' => '', 'label' => 'Any'],
            ['value' => 'male', 'label' => 'Male'],
            ['value' => 'female', 'label' => 'Female'],
            ['value' => 'unknown', 'label' => 'Unknown'],
        ];

        $sortOptions = [
            ['value' => 'newest', 'label' => 'Newest'],
            ['value' => 'oldest', 'label' => 'Oldest'],
            ['value' => 'name_asc', 'label' => 'Name A-Z'],
            ['value' => 'name_desc', 'label' => 'Name Z-A'],
            ['value' => 'weight_desc', 'label' => 'Heaviest'],
        ];

        $totalCount = $pets->total();
        $adoptionCount = $pets->getCollection()->filter(static function ($pet): bool {
            return (bool) ($pet->is_adoptable ?? $pet->is_for_adoption ?? false);
        })->count();
    @endphp

    <x-slot name="header">
        <x-ui.page-header title="Explore Pets" subtitle="Discover pet profiles across the community.">
            <x-slot name="action">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button :href="route('pets.adopt')" variant="outline" size="sm">Browse Adoption</x-ui.button>
                    @auth
                        <x-ui.button :href="route('pets.create')" variant="primary" size="sm">Create Pet Profile</x-ui.button>
                    @endauth
                </div>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="space-y-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.stat label="Total Results" :value="number_format($totalCount)" icon="🐾" />
            <x-ui.stat label="Adoptable in Results" :value="number_format($adoptionCount)" icon="🏡" />
            <x-ui.stat label="Browse Mode" value="Public Profiles" icon="🧭" />
        </div>

        <x-ui.card>
            <form method="GET" action="{{ route('pets.explore') }}" class="grid gap-3 md:grid-cols-12">
                <x-ui.input
                    class="md:col-span-4"
                    name="q"
                    label="Search"
                    :value="$filters['q']"
                    placeholder="Name, species, breed"
                />

                <x-ui.select
                    class="md:col-span-3"
                    name="species"
                    label="Species"
                    :options="$speciesOptions"
                    :selected="$filters['species']"
                />

                <x-ui.input
                    class="md:col-span-2"
                    name="breed"
                    label="Breed"
                    :value="$filters['breed']"
                />

                <x-ui.select
                    class="md:col-span-3"
                    name="sort"
                    label="Sort"
                    :options="$sortOptions"
                    :selected="$filters['sort']"
                />

                <x-ui.select
                    class="md:col-span-3"
                    name="sex"
                    label="Sex"
                    :options="$sexOptions"
                    :selected="$filters['sex']"
                />

                <div class="flex items-end md:col-span-5">
                    <label class="inline-flex items-center gap-2 rounded-md border border-whisker/40 bg-warm-white px-3 py-2 text-sm text-fur">
                        <input type="checkbox" name="is_adoptable" value="1" class="rounded border-whisker text-paw focus:ring-paw" @checked($filters['is_adoptable'])>
                        Show only adoptable pets
                    </label>
                </div>

                <div class="flex items-end md:col-span-2">
                    <x-ui.button type="submit" variant="primary" size="sm" class="w-full">Apply Filters</x-ui.button>
                </div>

                <div class="flex items-end md:col-span-2">
                    <x-ui.button :href="route('pets.explore')" variant="ghost" size="sm" class="w-full">Reset</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        @if ($pets->isEmpty())
            <x-ui.card>
                <x-ui.empty-state
                    icon="🐾"
                    title="No Pets Found"
                    description="Try different filters to find matching pet profiles."
                />
            </x-ui.card>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($pets as $pet)
                    @php
                        $petSlug = $pet->slug ?? $pet->getKey();
                        $isAdoptable = (bool) ($pet->is_adoptable ?? $pet->is_for_adoption ?? false);
                        $locationLabel = $isAdoptable ? 'Open for adoption' : 'Profile only';
                        $imageUrl = $pet->avatar_url;
                    @endphp

                    <x-pet-card
                        :name="$pet->name ?? 'Unnamed pet'"
                        :species="\Illuminate\Support\Str::headline((string) ($pet->species ?? 'Unknown'))"
                        :breed="$pet->breed ?: 'Mixed'"
                        :age="$pet->age_formatted ?: \Illuminate\Support\Str::headline((string) ($pet->sex ?? 'unknown'))"
                        :location="$locationLabel"
                        :image="$imageUrl"
                        :owner="$pet->owner?->name"
                        cta-label="View Profile"
                        :cta-href="route('pets.show', $petSlug)"
                    />
                @endforeach
            </div>

            <x-ui.card>
                <x-ui.pagination :paginator="$pets" />
            </x-ui.card>
        @endif
    </div>
</x-app-layout>
