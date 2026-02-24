@props(['post'])

@php
 $photos = $post->getMedia('photos');
@endphp

@if ($photos->isNotEmpty())
 <div class="grid grid-cols-2 gap-1">
 @foreach ($photos as $photo)
 <img src="{{ $photo->getUrl('medium') ?: $photo->getUrl() }}" alt="{{ $post->author->name }}'s photo" class="h-56 w-full object-cover"loading="lazy">
 @endforeach
 </div>
@endif
