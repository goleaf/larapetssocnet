<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 leading-tight">Marketplace</h2>
                <p class="mt-1 text-sm text-gray-600">Browse listings, filter results, and contact sellers directly.</p>
            </div>

            @auth
                <div class="flex items-center gap-2">
                    <a href="{{ route('messages.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Messages</a>
                    <a href="{{ route('marketplace.my-listings') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">My Listings</a>
                    <a href="{{ route('marketplace.create') }}" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Create Listing</a>
                </div>
            @endauth
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('marketplace.index') }}" class="grid gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-6">
                <div class="md:col-span-2">
                    <label for="q" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Search</label>
                    <input id="q" name="q" type="text" value="{{ request('q') }}" placeholder="Title, description, location"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="listing_type" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Type</label>
                    <select id="listing_type" name="listing_type" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All</option>
                        @foreach ($typeOptions as $type)
                            <option value="{{ $type }}" @selected(request('listing_type') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Status</label>
                    <select id="status" name="status" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="{{ \App\Models\MarketplaceListing::STATUS_ACTIVE }}" @selected($status === \App\Models\MarketplaceListing::STATUS_ACTIVE)>Active</option>
                        <option value="{{ \App\Models\MarketplaceListing::STATUS_SOLD }}" @selected($status === \App\Models\MarketplaceListing::STATUS_SOLD)>Sold</option>
                    </select>
                </div>

                <div>
                    <label for="sort" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Sort</label>
                    <select id="sort" name="sort" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="newest" @selected($sort === 'newest')>Newest</option>
                        <option value="oldest" @selected($sort === 'oldest')>Oldest</option>
                        <option value="price_low" @selected($sort === 'price_low')>Price: Low to High</option>
                        <option value="price_high" @selected($sort === 'price_high')>Price: High to Low</option>
                        <option value="most_viewed" @selected($sort === 'most_viewed')>Most Viewed</option>
                    </select>
                </div>

                <div>
                    <label for="location" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Location</label>
                    <input id="location" name="location" type="text" value="{{ request('location') }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="min_price" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Min Price</label>
                    <input id="min_price" name="min_price" type="number" min="0" step="0.01" value="{{ request('min_price') }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="max_price" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Max Price</label>
                    <input id="max_price" name="max_price" type="number" min="0" step="0.01" value="{{ request('max_price') }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="md:col-span-6 flex justify-end gap-2">
                    <a href="{{ route('marketplace.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                    <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Apply Filters</button>
                </div>
            </form>

            @if ($listings->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-600">
                    No listings found for the selected filters.
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($listings as $listing)
                        <x-marketplace-card
                            :title="$listing->title"
                            :price="$listing->formatted_price ?: 'Price on request'"
                            :condition="ucfirst($listing->listing_type ?: 'Listing')"
                            :location="$listing->location_text ?: 'Location not provided'"
                            :seller="$listing->seller?->name ?: 'Unknown seller'"
                            :image="$listing->cover_photo_url ?: null"
                            cta-label="View Listing"
                            :cta-href="route('marketplace.show', $listing)"
                        />
                    @endforeach
                </div>

                <div>
                    {{ $listings->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
