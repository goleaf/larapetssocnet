@php
 use Illuminate\Support\Carbon;

 $pet = $pet ?? null;
 $personalityTagsValue = old('personality_tags');
 if ($personalityTagsValue === null && $pet) {
 $existingTags = $pet->personality_tags ?? [];
 if (is_array($existingTags)) {
 $personalityTagsValue = implode(',', $existingTags);
 } else {
 $personalityTagsValue = (string) $existingTags;
 }
 }

 $birthdateValue = old('birth_date', old('birthdate'));
 if ($birthdateValue === null && $pet) {
 $rawBirthdate = data_get($pet,'birth_date') ?? data_get($pet,'birthdate');

 if ($rawBirthdate instanceof \Illuminate\Support\CarbonInterface) {
 $birthdateValue = $rawBirthdate->toDateString();
 } elseif (is_string($rawBirthdate) && $rawBirthdate !=='') {
 try {
 $birthdateValue = Carbon::parse($rawBirthdate)->toDateString();
 } catch (Throwable) {
 $birthdateValue = substr($rawBirthdate, 0, 10);
 }
 }
 }
@endphp

<div class="space-y-6">
 <div class="grid gap-6 sm:grid-cols-2">
 <div>
 <x-input-label for="name" value="Pet name"/>
 <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $pet?->name)" required />
 <x-input-error :messages="$errors->get('name')" class="mt-2"/>
 </div>

 <div>
 <x-input-label for="species" value="Species"/>
 <x-text-input id="species" name="species" type="text" class="mt-1 block w-full" :value="old('species', $pet?->species)" required />
 <x-input-error :messages="$errors->get('species')" class="mt-2"/>
 </div>

 <div>
 <x-input-label for="breed" value="Breed"/>
 <x-text-input id="breed" name="breed" type="text" class="mt-1 block w-full" :value="old('breed', $pet?->breed)"/>
 <x-input-error :messages="$errors->get('breed')" class="mt-2"/>
 </div>

 <div>
 <x-input-label for="sex" value="Sex"/>
 <select id="sex" name="sex" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
 <option value="">Select</option>
 <option value="male" @selected(old('sex', old('gender', $pet?->sex)) ==='male')>Male</option>
 <option value="female" @selected(old('sex', old('gender', $pet?->sex)) ==='female')>Female</option>
 <option value="unknown" @selected(old('sex', old('gender', $pet?->sex)) ==='unknown')>Unknown</option>
 </select>
 <x-input-error :messages="$errors->get('sex')" class="mt-2"/>
 </div>

 <div>
 <x-input-label for="birth_date" value="Birth date"/>
 <x-text-input id="birth_date" name="birth_date" type="date" class="mt-1 block w-full" :value="$birthdateValue"/>
 <x-input-error :messages="$errors->get('birth_date')" class="mt-2"/>
 </div>

 <div>
 <x-input-label for="age_text" value="Approx age (if birth date unknown)"/>
 <x-text-input id="age_text" name="age_text" type="text" class="mt-1 block w-full" :value="old('age_text', $pet?->age_text)" placeholder="~2 years"/>
 <x-input-error :messages="$errors->get('age_text')" class="mt-2"/>
 </div>

 <div>
 <x-input-label for="size" value="Size"/>
 <select id="size" name="size" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
 <option value="">Select</option>
 <option value="small" @selected(old('size', $pet?->size) ==='small')>Small</option>
 <option value="medium" @selected(old('size', $pet?->size) ==='medium')>Medium</option>
 <option value="large" @selected(old('size', $pet?->size) ==='large')>Large</option>
 <option value="xlarge" @selected(old('size', $pet?->size) ==='xlarge')>XLarge</option>
 </select>
 <x-input-error :messages="$errors->get('size')" class="mt-2"/>
 </div>
 </div>

 <div>
 <x-input-label for="bio" value="Bio"/>
 <textarea id="bio" name="bio" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('bio', $pet?->bio) }}</textarea>
 <x-input-error :messages="$errors->get('bio')" class="mt-2"/>
 </div>

 <div>
 <x-input-label for="personality_tags" value="Personality tags"/>
 <x-text-input
 id="personality_tags"
 name="personality_tags"
 type="text"
 class="mt-1 block w-full"
 :value="$personalityTagsValue"
 placeholder="playful, curious, friendly"
 />
 <p class="mt-1 text-xs text-gray-500">Comma-separated tags.</p>
 <x-input-error :messages="$errors->get('personality_tags')" class="mt-2"/>
 </div>

 <div>
 <x-input-label for="gallery_photos" value="Photo Gallery"/>
 <input
 id="gallery_photos"
 name="gallery_photos[]"
 type="file"
 multiple
 accept="image/jpeg,image/png,image/webp,image/gif"
 class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-700 hover:file:bg-indigo-100"
 />
 <p class="mt-1 text-xs text-gray-500">Upload up to 30 photos, max 5MB each.</p>
 <x-input-error :messages="$errors->get('gallery_photos')" class="mt-2"/>
 <x-input-error :messages="$errors->get('gallery_photos.*')" class="mt-2"/>

 @if (!empty($pet))
 @php $existingGallery = $pet->getMedia('gallery'); @endphp
 @if ($existingGallery->isNotEmpty())
 <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
 @foreach ($existingGallery->take(8) as $media)
 <img src="{{ $media->getUrl() }}" alt="Pet gallery image" class="h-20 w-full rounded-lg border border-gray-200 object-cover">
 @endforeach
 </div>
 @endif
 @endif
 </div>

 <div class="space-y-3">
 <label class="inline-flex items-center gap-2">
 <input type="checkbox" name="is_public" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_public', $pet?->is_public ?? true))>
 <span class="text-sm text-gray-700">Public profile</span>
 </label>

 <label class="inline-flex items-center gap-2">
 <input type="checkbox" name="is_adoptable" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_adoptable', old('is_for_adoption', $pet?->is_adoptable ?? false)))>
 <span class="text-sm text-gray-700">Available for adoption</span>
 </label>

 <label class="inline-flex items-center gap-2">
 <input type="checkbox" name="is_deceased" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_deceased', $pet?->is_deceased ?? false))>
 <span class="text-sm text-gray-700">Mark as deceased (Rainbow Bridge)</span>
 </label>
 </div>
</div>
