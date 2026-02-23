<x-app-layout>
    @php
        $listingType = (string) request('listing_type', '');

        $typeOptions = collect($typeOptions)
            ->map(static fn($type): array => [
                'value' => $type,
                'label' => \Illuminate\Support\Str::headline((string) $type),
            ])
            ->prepend([
                'value' => '',
                'label' => 'All types',
            ])
            ->values()
            ->all();

        $statusOptions = [
            ['value' => \App\Models\MarketplaceListing::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => \App\Models\MarketplaceListing::STATUS_SOLD, 'label' => 'Sold'],
        ];

        $sortOptions = [
            ['value' => 'newest', 'label' => 'Newest'],
            ['value' => 'oldest', 'label' => 'Oldest'],
            ['value' => 'price_low', 'label' => 'Price: Low to High'],
            ['value' => 'price_high', 'label' => 'Price: High to Low'],
            ['value' => 'most_viewed', 'label' => 'Most Viewed'],
        ];
    @endphp

    <x-slot name="header">
        <x-ui.page-header title="Marketplace" subtitle="Browse listings and contact sellers directly.">
            <x-slot name="action">
                @auth
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button :href="route('messages.index')" variant="outline" size="sm">Messages</x-ui.button>
                        <x-ui.button :href="route('marketplace.my-listings')" variant="outline" size="sm">My
                            Listings</x-ui.button>
                        <x-ui.button :href="route('marketplace.create')" variant="primary" size="sm">Create
                            Listing</x-ui.button>
                    </div>
                @endauth
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="space-y-5">
        <x-ui.card>
            <form method="GET" action="{{ route('marketplace.index') }}" class="grid gap-3 md:grid-cols-12">
                <x-ui.input class="md:col-span-4" name="q" label="Search" :value="request('q')"
                    placeholder="Title, description, location" />

                <x-ui.select class="md:col-span-2" name="listing_type" label="Type" :options="$typeOptions"
                    :selected="$listingType" />

                <x-ui.select class="md:col-span-2" name="status" label="Status" :options="$statusOptions"
                    :selected="$status" />

                <x-ui.select class="md:col-span-2" name="sort" label="Sort" :options="$sortOptions" :selected="$sort" />

                <x-ui.input class="md:col-span-2" name="location" label="Location" :value="request('location')" />

                <x-ui.input class="md:col-span-2" name="min_price" type="number" min="0" step="0.01" label="Min Price"
                    :value="request('min_price')" />

                <x-ui.input class="md:col-span-2" name="max_price" type="number" min="0" step="0.01" label="Max Price"
                    :value="request('max_price')" />

                <div class="md:col-span-8"></div>

                <div class="flex items-end md:col-span-2">
                    <x-ui.button type="submit" variant="primary" size="sm" class="w-full">Apply Filters</x-ui.button>
                </div>

                <div class="flex items-end md:col-span-2">
                    <x-ui.button :href="route('marketplace.index')" variant="ghost" size="sm"
                        class="w-full">Reset</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        @if ($listings->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="🛍️" title="No Listings Found"
                    description="Try adjusting your filters to see more results." />
            </x-ui.card>
        @else
            <p class="text-sm text-fur">{{ number_format($listings->total()) }} listings found</p>

            <div class="mt-4 flex flex-col gap-4 max-w-4xl mx-auto">
                @foreach ($listings as $listing)
                    <x-marketplace-card :title="$listing->title" :price="$listing->formatted_price ?: 'Price on request'"
                        :condition="ucfirst($listing->listing_type ?: 'Listing')" :location="$listing->location_text ?: 'Location not provided'" :seller="$listing->seller?->name ?: 'Unknown seller'"
                        :image="$listing->cover_photo_url ?: null" cta-label="View Listing"
                        :cta-href="route('marketplace.show', $listing)" />
                @endforeach
            </div>

            <x-ui.card>
                <x-ui.pagination :paginator="$listings" />
            </x-ui.card>
        @endif
    </div>
</x-app-layout>