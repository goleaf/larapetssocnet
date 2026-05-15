@php
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
@endphp

<div class="grid gap-6 md:grid-cols-2">
 <div class="md:col-span-2">
 <x-ui.input id="title" name="title" type="text" label="Title" :value="old('title', $listing->title)" required maxlength="140"/>
 </div>

 <div class="md:col-span-2">
 <x-ui.textarea id="description" name="description" rows="6" label="Description" :value="old('description', $listing->description)" required/>
 </div>

 <div>
 <x-ui.select id="listing_type" name="listing_type" label="Listing Type" :options="$typeOptions" :selected="old('listing_type', $listing->listing_type)" required/>
 </div>

 <div>
 <x-ui.select id="status" name="status" label="Status" :options="$statusOptions" :selected="old('status', $listing->status)" required/>
 </div>

 <div>
 <x-ui.input id="price" name="price" type="number" step="0.01" min="0" label="Price" :value="old('price', $listing->price)"/>
 </div>

 <div>
 <x-ui.input id="currency" name="currency" type="text" maxlength="3" label="Currency" :value="old('currency', $listing->currency ?: 'USD')" class="uppercase"/>
 </div>

 <div>
 <x-ui.input id="location_text" name="location_text" type="text" label="Location" :value="old('location_text', $listing->location_text)"/>
 </div>

 <div>
 <x-ui.input id="pet_id" name="pet_id" type="number" min="1" label="Pet ID (optional)" :value="old('pet_id', $listing->pet_id)"/>
 </div>

 <div>
 <x-ui.input id="contact_phone" name="contact_phone" type="text" label="Contact Phone" :value="old('contact_phone', $listing->contact_phone)"/>
 </div>

 <div>
 <x-ui.input id="contact_email" name="contact_email" type="email" label="Contact Email" :value="old('contact_email', $listing->contact_email)"/>
 </div>

 <div class="md:col-span-2">
 <x-ui.file-upload id="cover_image" name="cover_image" label="Cover Image" accept="image/*"/>

 @if ($listing->exists && $listing->getFirstMediaUrl('cover'))
 <div class="mt-3 overflow-hidden rounded-lg border border-gray-200">
 <img src="{{ $listing->getFirstMediaUrl('cover') }}" alt="Current cover" class="h-48 w-full object-cover">
 </div>

 <x-ui.checkbox name="remove_cover_image" label="Remove current cover image" :checked="old('remove_cover_image')"/>
 @endif
 </div>

 <div class="md:col-span-2">
 <x-ui.file-upload id="gallery_images" name="gallery_images[]" label="Gallery Images" accept="image/*" multiple/>

 @if ($listing->exists)
 @php
 $existingGallery = $listing->getMedia('gallery')->merge($listing->getMedia('images'));
 @endphp

 @if ($existingGallery->isNotEmpty())
 <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
 @foreach ($existingGallery as $media)
 <img src="{{ $media->getUrl() }}" alt="Listing gallery image" class="h-24 w-full rounded-lg border border-gray-200 object-cover">
 @endforeach
 </div>

 <x-ui.checkbox name="replace_gallery" label="Replace existing gallery when uploading new images" :checked="old('replace_gallery')"/>
 @endif
 @endif
 </div>
</div>
