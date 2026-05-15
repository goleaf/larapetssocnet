@php
 $listing = $listing ?? new \App\Models\Marketplace\MarketplaceListing([
'status'=> \App\Models\Marketplace\MarketplaceListing::STATUS_DRAFT,
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
 \App\Models\Marketplace\MarketplaceListing::STATUS_DRAFT =>'Draft',
 \App\Models\Marketplace\MarketplaceListing::STATUS_ACTIVE =>'Active',
 \App\Models\Marketplace\MarketplaceListing::STATUS_SOLD =>'Sold',
 \App\Models\Marketplace\MarketplaceListing::STATUS_ARCHIVED =>'Archived',
 ];

 $indexHref = Route::has('listings.index')
 ? route('listings.index')
 : (Route::has('marketplace.my-listings') ? route('marketplace.my-listings') :'#');

 $storeAction = Route::has('listings.store')
 ? route('listings.store')
 : (Route::has('marketplace.store') ? route('marketplace.store') :'#');

 $initialState = [
'title'=> old('title', data_get($listing,'title','')),
'description'=> old('description', data_get($listing,'description','')),
'type'=> old('listing_type', data_get($listing,'listing_type','adoption')),
'status'=> old('status', data_get($listing,'status', \App\Models\Marketplace\MarketplaceListing::STATUS_DRAFT)),
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
 title="Create Listing"
 description="Publish a new listing with clear details and a polished preview."
 eyebrow="Listing Manager"
 icon="📝"
 >
 <x-slot name="actions">
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
 <form method="POST" action="{{ $storeAction }}" enctype="multipart/form-data" class="space-y-5">
 @csrf

 <x-ui.form-section
 title="Basics"
 description="Tell people what you offer and why it matters."
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
 description="Set how this listing appears in search and feeds."
 icon="💸"
 >
 <div class="grid gap-4 md:grid-cols-2">
 <x-ui.select
 name="status"
 label="Status"
 :options="$statusOptions"
 :value="old('status', data_get($listing,'status', \App\Models\Marketplace\MarketplaceListing::STATUS_DRAFT))"
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
 description="Help interested people reach you quickly."
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
 description="Images improve trust and conversion."
 icon="🖼️"
 >
 <div class="grid gap-4">
 <x-ui.file-upload
 name="cover_image"
 label="Cover Image"
 accept="image/*"
 help="Recommended: landscape image, at least 1200px wide."
 />

 <x-ui.file-upload
 name="gallery_images[]"
 label="Gallery Images"
 accept="image/*"
 multiple
 help="Upload up to 12 images."
 />
 </div>
 </x-ui.form-section>

 <div class="flex flex-wrap items-center justify-end gap-2">
 <x-ui.button variant="ghost" :href="$indexHref">Cancel</x-ui.button>
 <x-ui.button type="submit">Create listing</x-ui.button>
 </div>
 </form>

 <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
 <x-ui.form-section
 title="Live Preview"
 description="This card updates as you type."
 icon="✨"
 >
 <article class="shell-card overflow-hidden">
 <div class="aspect-[16/10] border-b" style="border-color: var(--ui-border); background: color-mix(in srgb, var(--ui-secondary) 12%, var(--ui-surface) 88%);">
 <div class="flex h-full items-center justify-center text-4xl">🐾</div>
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
</x-app-layout>
