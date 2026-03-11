@php
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
@endphp

<div class="grid gap-6 md:grid-cols-2">
 <div class="md:col-span-2">
 <label for="title" class="block text-sm font-medium text-gray-400">Title</label>
 <input
 id="title"
 name="title"
 type="text"
 value="{{ old('title', $listing->title) }}"
 required
 maxlength="140"
 class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
 >
 <x-input-error :messages="$errors->get('title')" class="mt-2" />
 </div>

 <div class="md:col-span-2">
 <label for="description" class="block text-sm font-medium text-gray-400">Description</label>
 <textarea
 id="description"
 name="description"
 rows="6"
 required
 class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
 >{{ old('description', $listing->description) }}</textarea>
 <x-input-error :messages="$errors->get('description')" class="mt-2" />
 </div>

 <div>
 <label for="listing_type" class="block text-sm font-medium text-gray-400">Listing Type</label>
 <select
 id="listing_type"
 name="listing_type"
 required
 class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
 >
 @foreach ($typeOptions as $value => $label)
 <option value="{{ $value }}"@selected(old('listing_type', $listing->listing_type) === $value)>{{ $label }}</option>
 @endforeach
 </select>
 <x-input-error :messages="$errors->get('listing_type')" class="mt-2" />
 </div>

 <div>
 <label for="status" class="block text-sm font-medium text-gray-400">Status</label>
 <select
 id="status"
 name="status"
 required
 class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
 >
 @foreach ($statusOptions as $value => $label)
 <option value="{{ $value }}"@selected(old('status', $listing->status) === $value)>{{ $label }}</option>
 @endforeach
 </select>
 <x-input-error :messages="$errors->get('status')" class="mt-2" />
 </div>

 <div>
 <label for="price" class="block text-sm font-medium text-gray-400">Price</label>
 <input
 id="price"
 name="price"
 type="number"
 step="0.01"
 min="0"
 value="{{ old('price', $listing->price) }}"
 class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
 >
 <x-input-error :messages="$errors->get('price')" class="mt-2" />
 </div>

 <div>
 <label for="currency" class="block text-sm font-medium text-gray-400">Currency</label>
 <input
 id="currency"
 name="currency"
 type="text"
 maxlength="3"
 value="{{ old('currency', $listing->currency ?:'USD') }}"
 class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm uppercase focus:border-blue-500 focus:ring-blue-500"
 >
 <x-input-error :messages="$errors->get('currency')" class="mt-2" />
 </div>

 <div>
 <label for="location_text" class="block text-sm font-medium text-gray-400">Location</label>
 <input
 id="location_text"
 name="location_text"
 type="text"
 value="{{ old('location_text', $listing->location_text) }}"
 class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
 >
 <x-input-error :messages="$errors->get('location_text')" class="mt-2" />
 </div>

 <div>
 <label for="pet_id" class="block text-sm font-medium text-gray-400">Pet ID (optional)</label>
 <input
 id="pet_id"
 name="pet_id"
 type="number"
 min="1"
 value="{{ old('pet_id', $listing->pet_id) }}"
 class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
 >
 <x-input-error :messages="$errors->get('pet_id')" class="mt-2" />
 </div>

 <div>
 <label for="contact_phone" class="block text-sm font-medium text-gray-400">Contact Phone</label>
 <input
 id="contact_phone"
 name="contact_phone"
 type="text"
 value="{{ old('contact_phone', $listing->contact_phone) }}"
 class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
 >
 <x-input-error :messages="$errors->get('contact_phone')" class="mt-2" />
 </div>

 <div>
 <label for="contact_email" class="block text-sm font-medium text-gray-400">Contact Email</label>
 <input
 id="contact_email"
 name="contact_email"
 type="email"
 value="{{ old('contact_email', $listing->contact_email) }}"
 class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
 >
 <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
 </div>

 <div class="md:col-span-2">
 <label for="cover_image" class="block text-sm font-medium text-gray-400">Cover Image</label>
 <input
 id="cover_image"
 name="cover_image"
 type="file"
 accept="image/*"
 class="mt-1 block w-full text-sm text-gray-400"
 >
 <x-input-error :messages="$errors->get('cover_image')" class="mt-2" />

 @if ($listing->exists && $listing->getFirstMediaUrl('cover'))
 <div class="mt-3 overflow-hidden rounded-lg border border-gray-200">
 <img src="{{ $listing->getFirstMediaUrl('cover') }}" alt="Current cover" class="h-48 w-full object-cover">
 </div>

 <label class="mt-3 flex items-center gap-2 text-sm text-gray-400">
 <input type="checkbox" name="remove_cover_image" value="1"@checked(old('remove_cover_image'))>
 Remove current cover image
 </label>
 @endif
 </div>

 <div class="md:col-span-2">
 <label for="gallery_images" class="block text-sm font-medium text-gray-400">Gallery Images</label>
 <input
 id="gallery_images"
 name="gallery_images[]"
 type="file"
 multiple
 accept="image/*"
 class="mt-1 block w-full text-sm text-gray-400"
 >
 <x-input-error :messages="$errors->get('gallery_images')" class="mt-2" />
 <x-input-error :messages="$errors->get('gallery_images.*')" class="mt-2" />

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

 <label class="mt-3 flex items-center gap-2 text-sm text-gray-400">
 <input type="checkbox" name="replace_gallery" value="1"@checked(old('replace_gallery'))>
 Replace existing gallery when uploading new images
 </label>
 @endif
 @endif
 </div>
</div>
