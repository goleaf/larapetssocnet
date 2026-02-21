@php
    /** @var \App\Models\Post $post */
@endphp

<article id="post-{{ $post->id }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    <header class="flex items-start justify-between gap-4">
        <div>
            <p class="font-semibold text-gray-900">{{ $post->user->name }}</p>
            @if (! empty($post->user->username))
                <p class="text-sm text-gray-500">&#64;{{ $post->user->username }}</p>
            @endif
            <p class="text-xs text-gray-500">
                {{ $post->created_at?->diffForHumans() }}
                <span class="mx-1">&middot;</span>
                {{ ucfirst($post->visibility) }}
                @if ($post->is_pinned)
                    <span class="mx-1">&middot;</span>
                    <span class="font-semibold text-amber-600">Pinned</span>
                @endif
            </p>
        </div>

        <a href="{{ route('posts.show', $post) }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">Open</a>
    </header>

    @if (filled($post->body))
        <p class="mt-3 whitespace-pre-line text-gray-800">{{ $post->body }}</p>
    @endif

    @if ($post->hashtags->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($post->hashtags as $hashtag)
                <a
                    href="{{ route('hashtags.show', $hashtag) }}"
                    class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700"
                >
                    #{{ $hashtag->name }}
                </a>
            @endforeach
        </div>
    @endif

    @if ($post->location)
        <p class="mt-3 text-sm text-gray-600">Location: {{ $post->location }}</p>
    @endif

    @php
        $photos = $post->getMedia('photos');
        $video = $post->getFirstMedia('video');
    @endphp

    @if ($photos->isNotEmpty())
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($photos as $photo)
                <img
                    src="{{ $photo->getUrl() }}"
                    alt="Post photo"
                    class="h-56 w-full rounded-lg object-cover"
                    loading="lazy"
                >
            @endforeach
        </div>
    @endif

    @if ($video)
        <div class="mt-3">
            <video class="w-full rounded-lg" controls preload="metadata">
                <source src="{{ $video->getUrl() }}" type="{{ $video->mime_type }}">
            </video>
        </div>
    @endif

    <footer class="mt-4 flex flex-wrap items-center gap-3 text-sm text-gray-600">
        <span>{{ $post->likes_count }} reactions</span>
        <span>{{ $post->comments_count }} comments</span>
        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ strtoupper($post->type) }}</span>
    </footer>
</article>
