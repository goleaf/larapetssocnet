@section('title', 'Explore')

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="shell-kicker">Discover</p>
                <h1 class="shell-title text-2xl">Explore Public Posts</h1>
                <p class="mt-1 text-sm shell-text-muted">Find photos, videos, trending topics, and new creators.</p>
            </div>

            @auth
                <a href="{{ route('posts.create') }}" class="btn-base btn-primary px-3 py-2 text-sm">✚ New Post</a>
            @endauth
        </div>
    </x-slot>

    <div class="space-y-4">
        <section class="shell-panel p-4 sm:p-5">
            <form method="GET" action="{{ route('explore.index') }}" class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    class="form-input"
                    placeholder="Search posts, users, hashtags, or location"
                >

                <select name="type" class="form-select sm:min-w-[10rem]">
                    <option value="all" @selected($type === 'all')>All Posts</option>
                    <option value="photos" @selected($type === 'photos')>Photos</option>
                    <option value="videos" @selected($type === 'videos')>Videos</option>
                    <option value="trending" @selected($type === 'trending')>Trending (48h)</option>
                </select>

                <button type="submit" class="btn-base btn-primary px-4 py-2 text-sm">Apply</button>
            </form>

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (['all' => 'All', 'photos' => 'Photos', 'videos' => 'Videos', 'trending' => 'Trending'] as $option => $label)
                    <a
                        href="{{ route('explore.index', array_merge(request()->except('page', 'type'), ['type' => $option])) }}"
                        class="btn-base {{ $type === $option ? 'btn-primary' : 'btn-ghost' }} px-3 py-2 text-xs"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </section>

        @forelse ($posts as $post)
            @include('posts.partials.card', ['post' => $post])
        @empty
            <x-empty-state
                icon="🔎"
                title="No public posts found"
                description="Try a different search term, media type, or check back soon for new activity."
            />
        @endforelse

        <div class="shell-card p-3 sm:p-4">
            {{ $posts->links() }}
        </div>
    </div>
</x-app-layout>
