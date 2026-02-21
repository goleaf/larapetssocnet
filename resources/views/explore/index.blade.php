<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Explore - {{ config('app.name', 'LaraPets') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('explore.index') }}" class="text-lg font-semibold text-gray-900">{{ config('app.name', 'LaraPets') }}</a>
            <nav class="flex items-center gap-2 text-sm">
                @auth
                    <a href="{{ route('feed.index') }}" class="rounded-lg px-3 py-2 hover:bg-gray-100">Feed</a>
                    <a href="{{ route('saved.index') }}" class="rounded-lg px-3 py-2 hover:bg-gray-100">Saved</a>
                    <a href="{{ route('posts.create') }}" class="rounded-lg bg-blue-600 px-3 py-2 font-semibold text-white hover:bg-blue-700">New Post</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 hover:bg-gray-100">Login</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="py-8">
        <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('explore.index') }}" class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                    <input
                        type="text"
                        name="q"
                        value="{{ $search }}"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        placeholder="Search posts, users, hashtags, or location"
                    >

                    <select name="type" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="all" @selected($type === 'all')>All</option>
                        <option value="photos" @selected($type === 'photos')>Photos</option>
                        <option value="videos" @selected($type === 'videos')>Videos</option>
                        <option value="trending" @selected($type === 'trending')>Trending</option>
                    </select>

                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Apply</button>
                </form>
            </section>

            @forelse ($posts as $post)
                @include('posts.partials.card', ['post' => $post])
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-600">
                    No public posts found.
                </div>
            @endforelse

            <div>
                {{ $posts->links() }}
            </div>
        </div>
    </main>
</body>
</html>
