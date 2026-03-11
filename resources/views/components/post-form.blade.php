@props(['post'=> null])
<form action="{{ $post ? route('posts.update', $post) : route('posts.store') }}" method="POST"
 enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6">
 @csrf
 @if($post) @method('PATCH') @endif

 <!-- Body -->
 <div class="mb-4">
 <label for="body" class="block text-sm font-medium text-gray-700">What's on your mind?</label>
 <textarea name="body" id="body"rows="4"
 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('body', $post->body ??'') }}</textarea>
 @error('body') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
 </div>

 <!-- Visibility & Pet -->
 <div class="mb-4 grid gap-4 md:grid-cols-2">
 <div>
 <label class="block text-sm font-medium text-gray-700">Visibility</label>
 <div class="mt-1">
 <x-visibility-selector
 :selected="old('visibility', $post?->visibility ??' public')"
 :showWarn="$post !== null"
 :postLikes="$post?->likes_count ?? 0"
 :postComments="$post?->comments_count ?? 0"
 />
 </div>
 </div>

 <div>
 <label for="pet_id" class="block text-sm font-medium text-gray-700">Associate with Pet (Optional)</label>
 <select name="pet_id" id="pet_id"
 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
 <option value="">-- None --</option>
 @foreach(auth()->user()->pets as $pet)
 <option value="{{ $pet->id }}"@selected(old('pet_id', $post?->pet_id) == $pet->id)>{{ $pet->name }}
 </option>
 @endforeach
 </select>
 </div>
 </div>

 <!-- Location -->
 <div class="mb-4">
 <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
 <input type="text" name="location" id="location" value="{{ old('location', $post?->location) }}"
 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
 </div>

 @if(!$post)
 <!-- Media Uploads (Only on Create) -->
 <div class="mb-4 p-4 border rounded bg-gray-50">
 <div class="mb-3">
 <label class="block text-sm font-medium text-gray-700">Photos (Max 5)</label>
 <input type="file" name="photos[]" multiple accept="image/*"
 class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
 @error('photos.*') <span class="text-sm text-red-600 block">{{ $message }}</span> @enderror
 @error('photos') <span class="text-sm text-red-600 block">{{ $message }}</span> @enderror
 </div>

 <div>
 <label class="block text-sm font-medium text-gray-700">Or Video (Max 1, replaces photos)</label>
 <input type="file" name="video"accept="video/*"
 class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
 @error('video') <span class="text-sm text-red-600 block">{{ $message }}</span> @enderror
 </div>
 </div>
 @endif

 <div class="flex justify-end">
 <button type="submit"
 class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
 {{ $post ?'Update Post':'Post'}}
 </button>
 </div>
</form>
