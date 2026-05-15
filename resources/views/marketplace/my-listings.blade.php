<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="My Listings" description="Manage your marketplace inventory and listing status." icon="📦">
 <x-slot name="action">
 <div class="flex items-center gap-2">
 <x-ui.button :href="route('messages.index')" variant="ghost" size="sm">Messages</x-ui.button>
 <x-ui.button :href="route('marketplace.create')" variant="primary" size="sm">Create Listing</x-ui.button>
 </div>
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <div class="py-6">
 <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
 <x-ui.card padding="md">
 <form method="GET" action="{{ route('marketplace.my-listings') }}" class="grid gap-3 md:grid-cols-4">
 <div class="md:col-span-2">
 <x-ui.input id="q" name="q" label="Search" :value="request('q')" placeholder="Title, description, location"/>
 </div>

 <div>
 <x-ui.select
 id="status"
 name="status"
 label="Status"
 :options="[
 'all' => 'All',
 \App\Models\Marketplace\MarketplaceListing::STATUS_DRAFT => 'Draft',
 \App\Models\Marketplace\MarketplaceListing::STATUS_ACTIVE => 'Active',
 \App\Models\Marketplace\MarketplaceListing::STATUS_SOLD => 'Sold',
 \App\Models\Marketplace\MarketplaceListing::STATUS_ARCHIVED => 'Archived',
 ]"
 :selected="$status"
 />
 </div>

 <div>
 <x-ui.select
 id="sort"
 name="sort"
 label="Sort"
 :options="[
 'newest' => 'Newest',
 'oldest' => 'Oldest',
 'price_low' => 'Price: Low to High',
 'price_high' => 'Price: High to Low',
 'most_viewed' => 'Most Viewed',
 ]"
 :selected="$sort"
 />
 </div>

 <div class="md:col-span-4 flex justify-end gap-2">
 <x-ui.button :href="route('marketplace.my-listings')" variant="ghost">Reset</x-ui.button>
 <x-ui.button type="submit" variant="primary">Apply</x-ui.button>
 </div>
 </form>
 </x-ui.card>

 @if ($listings->isEmpty())
 <x-ui.card padding="lg" class="border-dashed">
 <div class="text-center text-sm text-gray-600">
 You do not have listings yet.
 </div>
 </x-ui.card>
 @else
 <div class="mt-4 flex flex-col gap-4 max-w-5xl mx-auto">
 @foreach ($listings as $listing)
 <x-ui.card padding="none" class="overflow-hidden">
 @if ($listing->cover_photo_url)
 <img src="{{ $listing->cover_photo_url }}" alt="{{ $listing->title }}"
 class="h-44 w-full object-cover">
 @else
 <div class="flex h-44 items-center justify-center text-4xl text-gray-400">🛍️</div>
 @endif

 <div class="space-y-3 p-4">
 <div class="flex items-start justify-between gap-2">
 <h3 class="font-semibold text-gray-900">{{ $listing->title }}</h3>
 <x-ui.badge variant="default" size="sm">{{ ucfirst($listing->status) }}</x-ui.badge>
 </div>

 <p class="text-sm text-blue-700 font-semibold">
 {{ $listing->formatted_price ?:'Price on request'}}</p>
 <p class="text-xs text-gray-500">{{ ucfirst($listing->listing_type ?:'listing') }} ·
 {{ $listing->location_text ?:'No location'}}</p>
 <p class="text-xs text-gray-500">Views: {{ number_format((int) $listing->views_count) }}</p>

 <div class="flex items-center gap-2">
 <x-ui.button :href="route('marketplace.show', $listing)" variant="ghost" class="flex-1">View</x-ui.button>
 <x-ui.button :href="route('marketplace.edit', $listing)" variant="primary" class="flex-1">Edit</x-ui.button>
 </div>

 <form method="POST" action="{{ route('marketplace.destroy', $listing) }}"
 onsubmit="return confirm('Delete this listing?')">
 @csrf
 @method('DELETE')
 <x-ui.button type="submit" variant="danger" class="w-full">Delete</x-ui.button>
 </form>
 </div>
 </x-ui.card>
 @endforeach
 </div>

 <div>
 {{ $listings->links() }}
 </div>
 @endif
 </div>
 </div>
</x-app-layout>
