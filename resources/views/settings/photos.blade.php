<x-settings-layout>
 <div class="space-y-6" data-ui="settings-photos-page">
 <div class="space-y-2" data-ui="settings-page-header">
 <p class="chip min-h-8">Media library</p>
 <h2 class="shell-title text-2xl">Photo Galleries</h2>
 <p class="max-w-2xl text-sm leading-6 shell-text-muted">
 Create galleries, upload photos, and choose a cover image for each gallery.
 </p>
 </div>

 <div class="space-y-8">
 <section>
 <h4 class="text-sm font-semibold text-bark">Create new gallery</h4>
 <form action="{{ route('settings.photos') === url()->current() ? route('photo-galleries.store') : route('photo-galleries.store') }}"
 method="POST"
 class="mt-4 space-y-4">
 @csrf
 <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
 <div class="sm:col-span-1">
 <x-ui.input id="title" name="title" type="text" label="Title" :value="old('title')" required/>
 </div>
 <div class="sm:col-span-1">
 <x-ui.textarea id="description" name="description" rows="2" label="Description (optional)" :value="old('description')"/>
 </div>
 </div>
 <div class="flex justify-end border-t border-whisker/30 pt-4">
 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-36">Create Gallery</x-ui.button>
 </div>
 </form>
 </section>

 <section>
 <h4 class="text-sm font-semibold text-bark">Your galleries</h4>

 @if ($galleries->isEmpty())
 <p class="mt-2 text-sm text-fur">
 You haven't created any galleries yet. Create your first gallery above, then add photos from your profile Photos tab.
 </p>
	 @else
	 <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
	 @foreach ($galleries as $gallery)
	 <div class="shell-card overflow-hidden">
	 @if ($gallery->coverUrl() !== '')
	 <img src="{{ $gallery->coverUrl() }}" alt="{{ $gallery->title }} cover"
	 class="h-32 w-full object-cover">
 @else
 <div class="flex h-32 w-full items-center justify-center bg-cream/60 text-3xl">
 📷
 </div>
 @endif
 <div class="px-4 py-3 space-y-1">
 <div class="flex items-start justify-between gap-2">
 <div class="min-w-0">
 <p class="truncate text-sm font-semibold text-bark">
 {{ $gallery->title }}
 </p>
 @if ($gallery->description)
 <p class="mt-0.5 line-clamp-2 text-xs text-fur">
 {{ $gallery->description }}
 </p>
 @endif
 </div>
 <x-ui.badge size="sm">
 {{ $gallery->media_count }} {{ Str::plural('photo', $gallery->media_count) }}
 </x-ui.badge>
 </div>

 @if ($gallery->media->isNotEmpty())
 <p class="mt-2 text-xs text-fur">
 To change the cover image or add more photos, use the Photos tab on your public profile.
 </p>
 @else
 <p class="mt-2 text-xs text-fur">
 No photos in this gallery yet. Add some from your profile Photos tab.
 </p>
 @endif
 </div>
 </div>
 @endforeach
 </div>
 @endif
 </section>
 </div>
 </div>
</x-settings-layout>
