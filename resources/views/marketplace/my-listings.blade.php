<x-app-layout>
 <x-slot name="header">
 <div class="flex flex-wrap items-center justify-between gap-3">
 <div>
 <h2 class="text-xl font-semibold text-gray-800 leading-tight">My Listings</h2>
 <p class="mt-1 text-sm text-gray-600">Manage your marketplace inventory and listing status.</p>
 </div>

 <div class="flex items-center gap-2">
 <a href="{{ route('messages.index') }}"
 class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Messages</a>
 <a href="{{ route('marketplace.create') }}"
 class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Create
 Listing</a>
 </div>
 </div>
 </x-slot>

 <div class="py-6">
 <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
 <form method="GET" action="{{ route('marketplace.my-listings') }}"
 class="grid gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-4">
 <div class="md:col-span-2">
 <label for="q"
 class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Search</label>
 <input id="q" name="q" type="text" value="{{ request('q') }}"
 placeholder="Title, description, location"
 class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
 </div>

 <div>
 <label for="status"
 class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Status</label>
 <select id="status" name="status"
 class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
 <option value="all"@selected($status ==='all')>All</option>
 <option value="{{ \App\Models\MarketplaceListing::STATUS_DRAFT }}"
 @selected($status === \App\Models\MarketplaceListing::STATUS_DRAFT)>Draft</option>
 <option value="{{ \App\Models\MarketplaceListing::STATUS_ACTIVE }}"
 @selected($status === \App\Models\MarketplaceListing::STATUS_ACTIVE)>Active</option>
 <option value="{{ \App\Models\MarketplaceListing::STATUS_SOLD }}"
 @selected($status === \App\Models\MarketplaceListing::STATUS_SOLD)>Sold</option>
 <option value="{{ \App\Models\MarketplaceListing::STATUS_ARCHIVED }}"
 @selected($status === \App\Models\MarketplaceListing::STATUS_ARCHIVED)>Archived</option>
 </select>
 </div>

 <div>
 <label for="sort"
 class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Sort</label>
 <select id="sort" name="sort"
 class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
 <option value="newest"@selected($sort ==='newest')>Newest</option>
 <option value="oldest"@selected($sort ==='oldest')>Oldest</option>
 <option value="price_low"@selected($sort ==='price_low')>Price: Low to High</option>
 <option value="price_high"@selected($sort ==='price_high')>Price: High to Low</option>
 <option value="most_viewed"@selected($sort ==='most_viewed')>Most Viewed</option>
 </select>
 </div>

 <div class="md:col-span-4 flex justify-end gap-2">
 <a href="{{ route('marketplace.my-listings') }}"
 class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
 <button type="submit"
 class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Apply</button>
 </div>
 </form>

 @if ($listings->isEmpty())
 <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-600">
 You do not have listings yet.
 </div>
 @else
 <div class="mt-4 flex flex-col gap-4 max-w-5xl mx-auto">
 @foreach ($listings as $listing)
 <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
 @if ($listing->cover_photo_url)
 <img src="{{ $listing->cover_photo_url }}" alt="{{ $listing->title }}"
 class="h-44 w-full object-cover">
 @else
 <div class="flex h-44 items-center justify-center text-4xl text-gray-400">🛍️</div>
 @endif

 <div class="space-y-3 p-4">
 <div class="flex items-start justify-between gap-2">
 <h3 class="font-semibold text-gray-900">{{ $listing->title }}</h3>
 <span
 class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ ucfirst($listing->status) }}</span>
 </div>

 <p class="text-sm text-blue-700 font-semibold">
 {{ $listing->formatted_price ?:'Price on request'}}</p>
 <p class="text-xs text-gray-500">{{ ucfirst($listing->listing_type ?:'listing') }} ·
 {{ $listing->location_text ?:'No location'}}</p>
 <p class="text-xs text-gray-500">Views: {{ number_format((int) $listing->views_count) }}</p>

 <div class="flex items-center gap-2">
 <a href="{{ route('marketplace.show', $listing) }}"
 class="inline-flex flex-1 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">View</a>
 <a href="{{ route('marketplace.edit', $listing) }}"
 class="inline-flex flex-1 items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Edit</a>
 </div>

 <form method="POST" action="{{ route('marketplace.destroy', $listing) }}"
 onsubmit="return confirm('Delete this listing?')">
 @csrf
 @method('DELETE')
 <button type="submit"
 class="inline-flex w-full items-center justify-center rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Delete</button>
 </form>
 </div>
 </article>
 @endforeach
 </div>

 <div>
 {{ $listings->links() }}
 </div>
 @endif
 </div>
 </div>
</x-app-layout>