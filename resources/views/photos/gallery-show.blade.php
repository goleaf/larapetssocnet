@php
 /** @var \App\Models\User $profileUser */
 /** @var \App\Models\PhotoGallery $gallery */
@endphp

@section('title', $gallery->title .'— @'. $profileUser->username .'— PetSocial')

<x-app-layout>
 <div class="space-y-5">
 <x-ui.card>
 <div class="flex items-center justify-between gap-3">
 <div>
 <p class="text-xs font-semibold text-fur mb-1">
 <a href="{{ route('profile.show', ['user'=> $profileUser,'tab'=>'photos']) }}"
 class="text-paw hover:underline">
 &larr; Back to Photos
 </a>
 </p>
 <h1 class="text-xl font-bold font-display text-bark">
 {{ $gallery->title }}
 </h1>
 <p class="mt-1 text-xs text-fur">
 In <a href="{{ route('profile.show', ['user'=> $profileUser]) }}"
 class="text-paw hover:underline">
 &#64;{{ $profileUser->username }}
 </a>'s gallery
 · {{ $gallery->media->count() }} {{ Str::plural('photo', $gallery->media->count()) }}
 </p>
 @if ($gallery->description)
 <p class="mt-2 text-sm text-fur">
 {{ $gallery->description }}
 </p>
 @endif
 </div>
 </div>
 </x-ui.card>

 <x-ui.card>
 @if ($gallery->media->isEmpty())
 <x-ui.empty-state icon="📷" title="No photos in this gallery yet"
 description="When photos are added to this gallery, they will appear here."/>
 @else
 <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
 @foreach ($gallery->media as $media)
 <div class="overflow-hidden rounded-xl border border-whisker/30 bg-warm-white shadow-sm">
 <img src="{{ $media->getUrl() }}"
 alt="{{ $gallery->title }} photo"
 class="h-64 w-full object-cover"
 loading="lazy"/>
 </div>
 @endforeach
 </div>
 @endif
 </x-ui.card>
 </div>
</x-app-layout>
