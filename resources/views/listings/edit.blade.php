@php
 $listing = $listing ?? new \App\Models\MarketplaceListing([
'status'=> \App\Models\MarketplaceListing::STATUS_DRAFT,
'currency'=>'USD',
'listing_type'=>'adoption',
 ]);

 $typeOptions = [
'adoption'=>'Adoption',
'sale'=>'Sale',
'service'=>'Service',
'accessory'=>'Accessory',
 ];

 $statusOptions = [
 \App\Models\MarketplaceListing::STATUS_DRAFT =>'Draft',
 \App\Models\MarketplaceListing::STATUS_ACTIVE =>'Active',
 \App\Models\MarketplaceListing::STATUS_SOLD =>'Sold',
 \App\Models\MarketplaceListing::STATUS_ARCHIVED =>'Archived',
 ];

 $indexHref = Route::has('listings.index')
 ? route('listings.index')
 : (Route::has('marketplace.my-listings') ? route('marketplace.my-listings') :'#');

 $showHref = Route::has('listings.show')
 ? route('listings.show', $listing)
 : (Route::has('marketplace.show') ? route('marketplace.show', $listing) :'#');

 $updateAction = Route::has('listings.update')
 ? route('listings.update', $listing)
 : (Route::has('marketplace.update') ? route('marketplace.update', $listing) :'#');

 $mediaDestroyRouteName = Route::has('listings.media.destroy')
 ?'listings.media.destroy'
 : (Route::has('marketplace.media.destroy') ?'marketplace.media.destroy': null);

 $coverMedia = null;

 if (is_object($listing) && method_exists($listing,'getMedia')) {
 $coverMedia = $listing->getMedia('cover')->first();
 }

 $coverImageUrl = trim((string) data_get($listing,'cover_photo_url',''));

 if ($coverImageUrl ===''&& $coverMedia && method_exists($coverMedia,'getUrl')) {
 $coverImageUrl = (string) $coverMedia->getUrl();
 }

 $existingGallery = collect($gallery ?? []);

 if ($existingGallery->isEmpty() && is_object($listing) && method_exists($listing,'getMedia')) {
 $existingGallery = $listing->getMedia('gallery')->merge($listing->getMedia('images'));
 }

 $initialState = [
'title'=> old('title', data_get($listing,'title','')),
'description'=> old('description', data_get($listing,'description','')),
'type'=> old('listing_type', data_get($listing,'listing_type','adoption')),
'status'=> old('status', data_get($listing,'status', \App\Models\MarketplaceListing::STATUS_DRAFT)),
'price'=> old('price', data_get($listing,'price','')),
'currency'=> strtoupper((string) old('currency', data_get($listing,'currency','USD'))),
'location'=> old('location_text', data_get($listing,'location_text','')),
'pet_id'=> old('pet_id', data_get($listing,'pet_id','')),
'contact_phone'=> old('contact_phone', data_get($listing,'contact_phone','')),
'contact_email'=> old('contact_email', data_get($listing,'contact_email','')),
 ];
@endphp

<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header
 title="Edit Listing"
 description="Update details, photos, and availability in one place."
 eyebrow="Listing Manager"
 icon="🛠️"
 >
 <x-slot name="actions">
 <x-ui.button variant="ghost" :href="$showHref">View listing</x-ui.button>
 <x-ui.button variant="ghost" :href="$indexHref">Back to listings</x-ui.button>
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <div
 class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_21rem]"
 x-data="{
 preview: @js($initialState),
 typeLabels: @js($typeOptions),
 statusLabels: @js($statusOptions),
 pricedTypes: ['sale','service','accessory'],
 get showPricing() {
 return this.pricedTypes.includes(this.preview.type);
 },
 get typeLabel() {
 return this.typeLabels[this.preview.type] ||'Listing';
 },
 get statusLabel() {
 return this.statusLabels[this.preview.status] ||'Draft';
 },
 get previewPrice() {
 const currency = (this.preview.currency ||'USD').toUpperCase();

 if (!this.showPricing) {
 return'Price hidden for this listing type';
 }

 if (this.preview.price === null || this.preview.price ===''|| Number(this.preview.price) <= 0) {
 return'Price on request';
 }

 const value = Number(this.preview.price);

 if (!Number.isFinite(value)) {
 return`${this.preview.price} ${currency}`;
 }

 return`${new Intl.NumberFormat().format(value)} ${currency}`;
 }
 }"
 >
 <form method="POST" action="{{ $updateAction }}" enctype="multipart/form-data" class="space-y-5">
 @csrf
 @method('PATCH')

 <x-ui.form-section
 title="Basics"
 description="Keep listing details accurate for better trust."
 icon="🐕"
 >
 <div class="grid gap-4 md:grid-cols-2">
 <x-ui.input
 class="md:col-span-2"
 name="title"
 label="Title"
 required
 maxlength="140"
 :value="old('title', data_get($listing,'title'))"
 placeholder="Golden retriever puppy looking for a home"
 x-model="preview.title"
 />

 <x-ui.textarea
 class="md:col-span-2"
 name="description"
 label="Description"
 required
 rows="5"
 :value="old('description', data_get($listing,'description'))"
 placeholder="Share temperament, health info, and what adopters should know."
 x-model="preview.description"
 />

 <x-ui.radio-group
 class="md:col-span-2"
 name="listing_type"
 label="Listing Type"
 :options="$typeOptions"
 :value="old('listing_type', data_get($listing,'listing_type','adoption'))"
 x-model="preview.type"
 />
 </div>
 </x-ui.form-section>

 <x-ui.form-section
 title="Pricing & Visibility"
 description="Control how this listing is displayed publicly."
 icon="💸"
 >
 <div class="grid gap-4 md:grid-cols-2">
 <x-ui.select
 name="status"
 label="Status"
 :options="$statusOptions"
 :value="old('status', data_get($listing,'status', \App\Models\MarketplaceListing::STATUS_DRAFT))"
 required
 x-model="preview.status"
 />

 <div
 class="rounded-xl border border-dashed p-3 text-sm"
 style="border-color: var(--ui-border-strong); background: color-mix(in srgb, var(--ui-surface-muted) 80%, var(--ui-surface) 20%);"
 >
 <p class="font-semibold" style="color: var(--ui-text);">Pricing behavior</p>
 <p class="mt-1 shell-text-muted" x-show="showPricing">Visible pricing is enabled for <span class="font-semibold" x-text="typeLabel"></span>.</p>
 <p class="mt-1 shell-text-muted" x-show="! showPricing">Pricing is hidden for this type. Buyers will see"Price on request".</p>
 </div>

 <div class="md:col-span-2" x-cloak x-show="showPricing">
 <div class="grid gap-4 md:grid-cols-2">
 <x-ui.input
 name="price"
 label="Price"
 type="number"
 min="0"
 step="0.01"
 :value="old('price', data_get($listing,'price'))"
 placeholder="0.00"
 x-model="preview.price"
 />

 <x-ui.input
 name="currency"
 label="Currency"
 maxlength="3"
 :value="strtoupper((string) old('currency', data_get($listing,'currency','USD')))"
 placeholder="USD"
 x-model="preview.currency"
 />
 </div>
 </div>
 </div>
 </x-ui.form-section>

 <x-ui.form-section
 title="Contact & Location"
 description="Make follow-up easy for interested adopters or buyers."
 icon="📍"
 >
 <div class="grid gap-4 md:grid-cols-2">
 <x-ui.input
 name="location_text"
 label="Location"
 :value="old('location_text', data_get($listing,'location_text'))"
 placeholder="Austin, TX"
 x-model="preview.location"
 />

 <x-ui.input
 name="pet_id"
 label="Pet ID"
 type="number"
 min="1"
 :value="old('pet_id', data_get($listing,'pet_id'))"
 placeholder="Optional"
 x-model="preview.pet_id"
 />

 <x-ui.input
 name="contact_phone"
 label="Contact Phone"
 :value="old('contact_phone', data_get($listing,'contact_phone'))"
 placeholder="+1 555 000 0000"
 x-model="preview.contact_phone"
 />

 <x-ui.input
 name="contact_email"
 label="Contact Email"
 type="email"
 :value="old('contact_email', data_get($listing,'contact_email'))"
 placeholder="you@example.com"
 x-model="preview.contact_email"
 />
 </div>
 </x-ui.form-section>

 <x-ui.form-section
 title="Photos"
 description="Upload new images and manage current media."
 icon="🖼️"
 >
 <div class="grid gap-4">
 <x-ui.file-upload
 name="cover_image"
 label="Replace Cover Image"
 accept="image/*"
 help="Uploading a new cover replaces the existing one."
 />

 @if ($coverImageUrl !=='')
 <x-ui.checkbox
 name="remove_cover_image"
 label="Remove current cover image"
 description="Current cover will be removed when you save."
 :checked="(bool) old('remove_cover_image')"
 />
 @endif

 <x-ui.file-upload
 name="gallery_images[]"
 label="Add Gallery Images"
 accept="image/*"
 multiple
 help="Upload up to 12 images."
 />

 @if ($existingGallery->isNotEmpty())
 <x-ui.checkbox
 name="replace_gallery"
 label="Replace gallery with newly uploaded files"
 description="Enable this to clear current gallery before adding new uploads."
 :checked="(bool) old('replace_gallery')"
 />
 @endif
 </div>
 </x-ui.form-section>

 <div class="flex flex-wrap items-center justify-end gap-2">
 <x-ui.button variant="ghost" :href="$showHref">Cancel</x-ui.button>
 <x-ui.button type="submit">Save changes</x-ui.button>
 </div>
 </form>

 <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
 <x-ui.form-section
 title="Live Preview"
 description="Confirm everything looks right before saving."
 icon="✨"
 >
 <article class="shell-card overflow-hidden">
 <div class="aspect-[16/10] border-b" style="border-color: var(--ui-border); background: color-mix(in srgb, var(--ui-secondary) 12%, var(--ui-surface) 88%);">
 @if ($coverImageUrl !=='')
 <img src="{{ $coverImageUrl }}" alt="Current cover" class="h-full w-full object-cover">
 @else
 <div class="flex h-full items-center justify-center text-4xl">🐾</div>
 @endif
 </div>

 <div class="space-y-3 p-4">
 <div class="flex items-start justify-between gap-2">
 <h3 class="shell-title text-base" x-text="preview.title ||'Untitled listing'"></h3>
 <x-ui.badge tone="info" x-text="statusLabel"></x-ui.badge>
 </div>

 <p class="shell-title text-lg" style="color: var(--ui-primary);" x-text="previewPrice"></p>

 <p class="text-sm shell-text-muted" x-text="preview.description ||'Add a clear description to attract more responses.'"></p>

 <div class="flex flex-wrap gap-2 text-xs shell-text-muted">
 <span class="chip" x-text="typeLabel"></span>
 <span x-text="preview.location ?`📍 ${preview.location}`:'📍 Location not provided'"></span>
 </div>
 </div>
 </article>
 </x-ui.form-section>
 </aside>
 </div>

 <div class="mt-5">
 <x-ui.form-section
 title="Existing Images"
 description="Manage currently uploaded files."
 icon="🗂️"
 >
 <div class="space-y-4">
 @if ($coverImageUrl !=='')
 <div class="rounded-xl border p-3" style="border-color: var(--ui-border);">
 <p class="mb-2 text-sm font-semibold" style="color: var(--ui-text);">Cover</p>

 <div class="flex flex-wrap items-center gap-3">
 <img src="{{ $coverImageUrl }}" alt="Cover image" class="h-20 w-28 rounded-lg object-cover">

 @if ($mediaDestroyRouteName && $coverMedia)
 <form method="POST" action="{{ route($mediaDestroyRouteName, [$listing, data_get($coverMedia,'id')]) }}">
 @csrf
 @method('DELETE')
 <x-ui.button variant="danger" size="sm" type="submit">Remove cover</x-ui.button>
 </form>
 @else
 <p class="text-xs shell-text-muted">Cover removal endpoint is not available. Use the checkbox above and save changes.</p>
 @endif
 </div>
 </div>
 @endif

 <div>
 <p class="mb-2 text-sm font-semibold" style="color: var(--ui-text);">Gallery</p>

 @if ($existingGallery->isEmpty())
 <p class="text-sm shell-text-muted">No gallery images uploaded.</p>
 @else
 <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
 @foreach ($existingGallery as $media)
 <div class="rounded-xl border p-2" style="border-color: var(--ui-border);">
 <img src="{{ $media->getUrl() }}" alt="Gallery image" class="h-28 w-full rounded-lg object-cover">

 @if ($mediaDestroyRouteName)
 <form method="POST" action="{{ route($mediaDestroyRouteName, [$listing, data_get($media,'id')]) }}" class="mt-2">
 @csrf
 @method('DELETE')
 <x-ui.button variant="danger" size="xs" type="submit" class="w-full">Remove</x-ui.button>
 </form>
 @else
 <p class="mt-2 text-xs shell-text-muted">Remove endpoint not available yet.</p>
 @endif
 </div>
 @endforeach
 </div>
 @endif
 </div>
 </div>
 </x-ui.form-section>
 </div>
</x-app-layout>
