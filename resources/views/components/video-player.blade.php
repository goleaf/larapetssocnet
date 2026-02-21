@props(['post'])

@php
    $video = $post->getFirstMedia('videos') ?? $post->getFirstMedia('video');
@endphp

@if ($video)
    <div class="mt-3 overflow-hidden rounded-xl border border-[var(--ui-border)]">
        <video controls preload="metadata" playsinline class="w-full max-h-[500px]" poster="{{ $post->getFirstMediaUrl('photos', 'thumb') }}">
            <source src="{{ $video->getUrl() }}" type="{{ $video->mime_type ?? 'video/mp4' }}">
        </video>
    </div>
@endif
