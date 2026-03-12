@props([
'post' => null,
'availablePets' => collect(),
])

<form action="{{ $post ? route('posts.update', $post) : route('posts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
 @csrf
 @if($post)
 @method('PATCH')
 @endif

 <x-ui.textarea
 name="body"
 id="body"
 rows="4"
 label="What's on your mind?"
 :value="old('body', $post->body ?? '')"
 required
 />
 @error('body')
 <span class="text-sm text-rose">{{ $message }}</span>
 @enderror

 <div class="grid gap-4 md:grid-cols-2">
 <div>
 <x-ui.label>Visibility</x-ui.label>
 <div class="mt-1">
 <x-visibility-selector
 :selected="old('visibility', $post?->visibility ?? 'public')"
 :showWarn="$post !== null"
 :postLikes="$post?->likes_count ?? 0"
 :postComments="$post?->comments_count ?? 0"
 />
 </div>
 </div>

 <x-ui.select
 id="pet_id"
 name="pet_id"
 label="Associate with Pet (Optional)"
 :value="old('pet_id', $post?->pet_id)"
 >
 <option value="">-- None --</option>
 @foreach(collect($availablePets) as $pet)
 <option value="{{ $pet->id }}" @selected((string) old('pet_id', $post?->pet_id) === (string) $pet->id)>{{ $pet->name }}</option>
 @endforeach
 </x-ui.select>
 </div>

 <x-ui.input
 type="text"
 name="location"
 id="location"
 label="Location"
 :value="old('location', $post?->location)"
 />

 @if(!$post)
 <x-ui.panel padding="md" class="bg-cream/50">
 <div class="space-y-4">
 <x-ui.file-upload name="photos[]" label="Photos (Max 5)" accept="image/*" multiple />
 @error('photos.*')
 <span class="text-sm text-rose block">{{ $message }}</span>
 @enderror
 @error('photos')
 <span class="text-sm text-rose block">{{ $message }}</span>
 @enderror

 <x-ui.file-upload name="video" label="Or Video (Max 1, replaces photos)" accept="video/*" />
 @error('video')
 <span class="text-sm text-rose block">{{ $message }}</span>
 @enderror
 </div>
 </x-ui.panel>
 @endif

 <div class="flex justify-end">
 <x-ui.button type="submit" variant="primary">
 {{ $post ? 'Update Post' : 'Post' }}
 </x-ui.button>
 </div>
</form>
