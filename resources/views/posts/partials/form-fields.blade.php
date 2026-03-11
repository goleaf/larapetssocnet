@php
 /** @var \App\Models\Post $post */
@endphp

<div class="space-y-4">
 <div>
 <label for="body" class="block text-sm font-medium text-gray-700">Body</label>
 <textarea
 id="body"
 name="body"
 rows="6"
 class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
 placeholder="Share something about your pet..."
 >{{ old('body', $post->body) }}</textarea>
 @error('body')
 <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
 @enderror
 </div>

 <div class="grid gap-4 sm:grid-cols-2">
 <div>
 <label for="visibility" class="block text-sm font-medium text-gray-700">Visibility</label>
 <select id="visibility" name="visibility" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
 @foreach ($visibilityOptions as $option)
 <option value="{{ $option }}" @selected(old('visibility', $post->visibility) === $option)>
 {{ ucfirst($option) }}
 </option>
 @endforeach
 </select>
 @error('visibility')
 <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
 @enderror
 </div>

 <div>
 <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
 <input
 id="location"
 name="location"
 type="text"
 value="{{ old('location', $post->location) }}"
 class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
 placeholder="City, park, or neighborhood"
 >
 @error('location')
 <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
 @enderror
 </div>
 </div>

 <div>
 <label for="tagged_pets" class="block text-sm font-medium text-gray-700">Tag Pets</label>
 @php
 $selectedPets = old('tagged_pets', $post->tagged_pets ?? []);
 $selectedPets = is_array($selectedPets) ? $selectedPets : [];
 @endphp
 <select id="tagged_pets" name="tagged_pets[]" multiple class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
 @foreach ($pets as $pet)
 <option value="{{ $pet->id }}" @selected(in_array($pet->id, array_map('intval', $selectedPets), true))>{{ $pet->name }}</option>
 @endforeach
 </select>
 <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple pets.</p>
 @error('tagged_pets')
 <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
 @enderror
 @error('tagged_pets.*')
 <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
 @enderror
 </div>

 <div class="grid gap-4 sm:grid-cols-2">
 <div>
 <label for="photos" class="block text-sm font-medium text-gray-700">Photos (multiple)</label>
 <input id="photos" name="photos[]" type="file" accept="image/*" multiple class="mt-1 block w-full text-sm text-gray-700">
 @error('photos')
 <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
 @enderror
 @error('photos.*')
 <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
 @enderror

 @if ($post->exists && ($post->getMedia('photos')->isNotEmpty() || $post->getMedia('images')->isNotEmpty()))
 <label class="mt-2 inline-flex items-center gap-2 text-xs text-gray-700">
 <input type="checkbox" name="remove_photos" value="1" class="rounded border-gray-300">
 Remove existing photos
 </label>
 @endif
 </div>

 <div>
 <label for="video" class="block text-sm font-medium text-gray-700">Video (single)</label>
 <input id="video" name="video" type="file" accept="video/mp4,video/quicktime,video/webm" class="mt-1 block w-full text-sm text-gray-700">
 @error('video')
 <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
 @enderror

 @if ($post->exists && $post->getFirstMedia('video'))
 <label class="mt-2 inline-flex items-center gap-2 text-xs text-gray-700">
 <input type="checkbox" name="remove_video" value="1" class="rounded border-gray-300">
 Remove existing video
 </label>
 @endif
 </div>
 </div>
</div>
