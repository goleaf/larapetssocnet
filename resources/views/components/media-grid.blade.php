@props(['post'])

@php
    $photos = $post->getMedia('photos');
@endphp

@if ($photos->isNotEmpty())
    <div class="mt-3 grid gap-2 {{ $photos->count() === 1 ? 'grid-cols-1' : 'grid-cols-2' }}">
        @foreach ($photos->take(5) as $index => $photo)
            <figure class="relative overflow-hidden rounded-xl">
                <img loading="lazy" src="{{ $photo->getUrl('medium') ?: $photo->getUrl() }}" alt="Post photo {{ $index + 1 }}" class="h-64 w-full object-cover">
                @if ($index === 4 && $photos->count() > 5)
                    <figcaption class="absolute inset-0 grid place-items-center bg-black/50 text-xl font-semibold text-white">+{{ $photos->count() - 5 }}</figcaption>
                @endif
            </figure>
        @endforeach
    </div>
@endif
