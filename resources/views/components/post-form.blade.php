@props([
'post' => null,
'availablePets' => collect(),
])

@php
 $statusValue = old('status', $post?->status?->value ?? 'published');
 $publishedAtValue = old('scheduled_publish_at', old('published_at', optional($post?->scheduled_publish_at ?? $post?->published_at)->format('Y-m-d\\TH:i')));
@endphp

<form action="{{ $post ? route('posts.update', $post) : route('posts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5"
 x-data="{ status: '{{ $statusValue }}' }">
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
 maxlength="1000"
 placeholder="Share a walk, a tiny victory, a question, or a moment worth remembering."
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

 <div>
 <x-ui.label for="status">Status</x-ui.label>
 <x-ui.select id="status" name="status" x-model="status">
 <option value="draft">Draft</option>
 <option value="published">Published</option>
 <option value="scheduled">Scheduled</option>
 @if($post)
 <option value="archived">Archived</option>
 @endif
 </x-ui.select>
 @error('status')
 <span class="text-sm text-rose">{{ $message }}</span>
 @enderror
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

 <div class="grid gap-4 md:grid-cols-2">
 <x-ui.input
 type="text"
 name="location"
 id="location"
 label="Location"
 :value="old('location', $post?->location)"
 />

 <x-ui.select
 id="mood"
 name="mood"
 label="Mood"
 :value="old('mood', $post?->mood)"
 >
 <option value="">No mood</option>
 @foreach(\App\Support\Posts\PostMood::DISPLAY as $moodValue => $moodDisplay)
 <option value="{{ $moodValue }}" @selected(old('mood', $post?->mood) === $moodValue)>{{ $moodDisplay['emoji'] }} {{ $moodDisplay['label'] }}</option>
 @endforeach
 </x-ui.select>
 </div>

 <div class="grid gap-4 md:grid-cols-2">

 <div x-show="status === 'scheduled'" x-cloak>
 <x-ui.input
 type="datetime-local"
 name="scheduled_publish_at"
 id="scheduled_publish_at"
 label="Publish At"
 :value="$publishedAtValue"
 />
 @error('scheduled_publish_at')
 <span class="text-sm text-rose">{{ $message }}</span>
 @enderror
 @error('published_at')
 <span class="text-sm text-rose">{{ $message }}</span>
 @enderror
 </div>
 </div>

 @if(!$post)
 <x-ui.panel padding="md" class="bg-cream/50">
 <div class="space-y-4">
 <x-ui.file-upload name="photos[]" label="Photos (Max 5)" accept="image/*" multiple max-size="10mb" preview />
 @error('media.photos.*')
 <span class="text-sm text-rose block">{{ $message }}</span>
 @enderror
 @error('photos')
 <span class="text-sm text-rose block">{{ $message }}</span>
 @enderror

 <x-ui.file-upload name="video" label="Or Video (Max 1, replaces photos)" accept="video/*" max-size="50mb" />
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
