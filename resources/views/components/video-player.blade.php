@props(['post'])

@php
 $videoUrl = $post->getFirstMediaUrl('videos');
@endphp

@if ($videoUrl)
 <div class="bg-black">
 <video src="{{ $videoUrl }}"controls class="max-h-[600px] w-full" aria-label="Video post by {{ $post->author->name }}"></video>
 </div>
@endif
