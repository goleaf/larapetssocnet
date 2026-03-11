@php
 $listings = $listings ?? collect();
 $isPaginator = $listings instanceof \Illuminate\Contracts\Pagination\Paginator;
 $listingItems = $isPaginator ? collect($listings->items()) : collect($listings);

 $activeTab = (string) ($activeTab ?? request('tab', request('status','all')));
 $activeTab = $activeTab !==''? $activeTab :'all';

 $computedCounts = [
'all'=> $listingItems->count(),
'active'=> $listingItems->where('status','active')->count(),
'draft'=> $listingItems->where('status','draft')->count(),
'sold'=> $listingItems->where('status','sold')->count(),
'archived'=> $listingItems->where('status','archived')->count(),
'deleted'=> $listingItems->filter(fn ($item) => filled(data_get($item,'deleted_at')))->count(),
 ];

 $tabCounts = array_merge($computedCounts, (array) ($tabCounts ?? []));

 $indexRouteName = Route::has('listings.index')
 ?'listings.index'
 : (Route::has('marketplace.my-listings') ?'marketplace.my-listings': null);

 $createHref = Route::has('listings.create')
 ? route('listings.create')
 : (Route::has('marketplace.create') ? route('marketplace.create') :'#');

 $messagesHref = Route::has('messages.index') ? route('messages.index') :'#';

 $showRouteName = Route::has('listings.show')
 ?'listings.show'
 : (Route::has('marketplace.show') ?'marketplace.show': null);

 $editRouteName = Route::has('listings.edit')
 ?'listings.edit'
 : (Route::has('marketplace.edit') ?'marketplace.edit': null);

 $destroyRouteName = Route::has('listings.destroy')
 ?'listings.destroy'
 : (Route::has('marketplace.destroy') ?'marketplace.destroy': null);

 $restoreRouteName = Route::has('listings.restore') ?'listings.restore': null;

 $baseQuery = request()->except('page');

 $tabs = collect([
 ['id'=>'all','label'=>'All'],
 ['id'=>'active','label'=>'Active'],
 ['id'=>'draft','label'=>'Drafts'],
 ['id'=>'sold','label'=>'Sold'],
 ['id'=>'archived','label'=>'Archived'],
 ['id'=>'deleted','label'=>'Deleted'],
 ])->map(function (array $tab) use ($tabCounts, $activeTab, $indexRouteName, $baseQuery): array {
 $tabId = $tab['id'];

 return [
'label'=> $tab['label'],
'count'=> (int) ($tabCounts[$tabId] ?? 0),
'active'=> $activeTab === $tabId,
'href'=> $indexRouteName
 ? route($indexRouteName, array_merge($baseQuery, ['tab'=> $tabId]))
 :'#',
 ];
 })->all();
@endphp

<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header
 title="My Listings"
 description="Track status, update pricing, and manage visibility for every listing."
 eyebrow="Listing Manager"
 icon="🦴"
 >
 <x-slot name="actions">
 <x-ui.button variant="ghost":href="$messagesHref">Messages</x-ui.button>
 <x-ui.button :href="$createHref">New Listing</x-ui.button>
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <div class="space-y-5">
 <x-ui.tabs :tabs="$tabs"/>

 @if ($listingItems->isEmpty())
 <x-ui.empty-state
 icon="🧺"
 title="No listings in this tab"
 description="Create your first listing and connect with pet lovers in your area."
 >
 <x-slot name="actions">
 <x-ui.button :href="$createHref">Create listing</x-ui.button>
 </x-slot>
 </x-ui.empty-state>
 @else
 <div class="grid gap-4 md:hidden">
 @foreach ($listingItems as $listing)
 <div class="space-y-2">
 @include('partials.listing-card', ['listing'=> $listing])

 @php
 $listingId = (int) data_get($listing,'id');
 $showHref = $showRouteName ? route($showRouteName, $listingId ?: $listing) :'#';
 $editHref = $editRouteName ? route($editRouteName, $listingId ?: $listing) :'#';
 @endphp

 <div class="flex gap-2">
 <x-ui.button variant="ghost"size="sm"class="flex-1":href="$showHref">View</x-ui.button>
 <x-ui.button size="sm"class="flex-1":href="$editHref">Edit</x-ui.button>
 </div>
 </div>
 @endforeach
 </div>

 <div class="hidden md:block">
 <x-ui.table :headings="['Listing','Type','Price','Status','Views','Updated','Actions']">
 @foreach ($listingItems as $listing)
 @php
 $listingId = (int) data_get($listing,'id');
 $listingTitle = (string) data_get($listing,'title','Untitled listing');
 $listingType = \Illuminate\Support\Str::headline((string) data_get($listing,'listing_type','listing'));
 $listingStatus = (string) data_get($listing,'status','draft');
 $isDeleted = filled(data_get($listing,'deleted_at'));

 $priceText = trim((string) data_get($listing,'formatted_price',''));

 if ($priceText ==='') {
 $rawPrice = data_get($listing,'price');
 $currency = strtoupper((string) data_get($listing,'currency','USD'));
 $priceText = $rawPrice !== null && $rawPrice !==''
 ? number_format((float) $rawPrice, 2).''.$currency
 :'Price on request';
 }

 $statusTone = match ($listingStatus) {
'active'=>'success',
'sold'=>'warning',
'archived'=>'neutral',
 default =>'info',
 };

 if ($isDeleted) {
 $statusTone ='danger';
 }

 $coverImageUrl = trim((string) data_get($listing,'cover_photo_url',''));

 if ($coverImageUrl ===''&& is_object($listing) && method_exists($listing,'getFirstMediaUrl')) {
 $coverImageUrl = (string) ($listing->getFirstMediaUrl('cover') ?: $listing->getFirstMediaUrl('gallery'));
 }

 $updatedAtRaw = data_get($listing,'updated_at');
 $updatedAtLabel = $updatedAtRaw
 ? \Illuminate\Support\Carbon::parse($updatedAtRaw)->diffForHumans()
 :'—';

 $showHref = $showRouteName ? route($showRouteName, $listingId ?: $listing) :'#';
 $editHref = $editRouteName ? route($editRouteName, $listingId ?: $listing) :'#';
 @endphp

 <x-ui.table-row :deleted="$isDeleted">
 <td class="px-4 py-4">
 <div class="flex items-center gap-3">
 <div class="h-12 w-12 overflow-hidden rounded-xl border"style="border-color: var(--ui-border); background: color-mix(in srgb, var(--ui-secondary) 12%, var(--ui-surface) 88%);">
 @if ($coverImageUrl !=='')
 <img src="{{ $coverImageUrl }}"alt="{{ $listingTitle }}"class="h-full w-full object-cover">
 @else
 <div class="flex h-full items-center justify-center text-lg">🐾</div>
 @endif
 </div>

 <div class="min-w-0">
 <p @class([
'truncate text-sm font-semibold',
'line-through'=> $isDeleted,
 ]) style="color: var(--ui-text);">{{ $listingTitle }}</p>
 <p class="truncate text-xs shell-text-muted">{{ \Illuminate\Support\Str::limit((string) data_get($listing,'description',''), 55) }}</p>
 </div>
 </div>
 </td>

 <td class="px-4 py-4 text-sm shell-text-muted">{{ $listingType }}</td>
 <td class="px-4 py-4 text-sm font-semibold"style="color: var(--ui-primary);">{{ $priceText }}</td>
 <td class="px-4 py-4">
 <div class="flex flex-wrap items-center gap-1.5">
 <x-ui.badge :tone="$statusTone">
 {{ $isDeleted ?'Deleted': \Illuminate\Support\Str::headline($listingStatus) }}
 </x-ui.badge>
 </div>
 </td>
 <td class="px-4 py-4 text-sm shell-text-muted">{{ number_format((int) data_get($listing,'views_count', 0)) }}</td>
 <td class="px-4 py-4 text-sm shell-text-muted">{{ $updatedAtLabel }}</td>

 <td class="px-4 py-4 text-right">
 <x-ui.dropdown align="right"width="48">
 <x-slot name="trigger">
 <x-ui.button variant="ghost"size="xs"type="button">Actions</x-ui.button>
 </x-slot>

 <x-slot name="content">
 <div class="space-y-1 p-1 text-sm">
 <a href="{{ $showHref }}"class="block rounded-lg px-3 py-2 hover:bg-black/5">View</a>

 @if (! $isDeleted && $editRouteName)
 <a href="{{ $editHref }}"class="block rounded-lg px-3 py-2 hover:bg-black/5">Edit</a>
 @endif

 @if ($isDeleted && $restoreRouteName)
 <form method="POST"action="{{ route($restoreRouteName, $listingId ?: $listing) }}">
 @csrf
 <button type="submit"class="block w-full rounded-lg px-3 py-2 text-left hover:bg-black/5">Restore</button>
 </form>
 @endif

 @if ($destroyRouteName)
 <button
 type="button"
 class="block w-full rounded-lg px-3 py-2 text-left hover:bg-black/5"
 x-on:click="window.toggleModal('delete-listing-{{ $listingId }}')"
 >
 Delete
 </button>
 @endif
 </div>
 </x-slot>
 </x-ui.dropdown>
 </td>
 </x-ui.table-row>
 @endforeach
 </x-ui.table>
 </div>

 @if ($destroyRouteName)
 @foreach ($listingItems as $listing)
 @php
 $listingId = (int) data_get($listing,'id');
 $listingTitle = (string) data_get($listing,'title','this listing');
 $deleteFormId ='delete-listing-'.$listingId;
 @endphp

 <form id="{{ $deleteFormId }}"method="POST"action="{{ route($destroyRouteName, $listingId ?: $listing) }}"class="hidden">
 @csrf
 @method('DELETE')
 </form>

 <x-ui.confirm-modal
 :name="'delete-listing-'.$listingId"
 title="Delete listing?"
 description="{{'You are about to delete'.$listingTitle.'. This can be restored only if your backend supports listing restore.'}}"
 confirm-label="Delete"
 :form-id="$deleteFormId"
 />
 @endforeach
 @endif

 @if ($isPaginator)
 <x-ui.pagination :paginator="$listings"class="mt-4"/>
 @endif
 @endif
 </div>
</x-app-layout>
