@section('title', 'Feed — PetSocial')

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="shell-kicker">Home Feed</p>
                <h1 class="shell-title text-2xl leading-tight">Your Feed</h1>
            </div>
            <a href="{{ route('posts.create') }}" class="btn-base btn-primary px-3 py-2 text-sm">Create Post</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[16rem,minmax(0,1fr),18rem]">
            <aside class="hidden lg:block">
                <div class="sticky top-20 space-y-4">
                    <div class="rounded-2xl border border-gray-200 bg-white p-4">
                        <div class="flex items-center gap-3">
                            <x-avatar :src="$user->avatar_url" :name="$user->name" size="md" />
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $user->name }}</p>
                                <p class="text-xs text-gray-500">@{{ $user->username }}</p>
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                            <div><p class="font-semibold text-gray-900">{{ $user->posts_count }}</p><p class="text-gray-500">Posts</p></div>
                            <div><p class="font-semibold text-gray-900">{{ $user->followers_count }}</p><p class="text-gray-500">Followers</p></div>
                            <div><p class="font-semibold text-gray-900">{{ $user->following_count }}</p><p class="text-gray-500">Following</p></div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-4 text-sm">
                        <ul class="space-y-2">
                            <li><a href="{{ route('pets.explore') }}" class="text-gray-700 hover:text-emerald-600">🐾 My Pets</a></li>
                            <li><a href="{{ route('saved.index') }}" class="text-gray-700 hover:text-emerald-600">🔖 Saved Posts</a></li>
                            <li><a href="{{ route('groups.index', ['tab' => 'mine']) }}" class="text-gray-700 hover:text-emerald-600">👥 My Groups</a></li>
                            <li><a href="{{ route('events.index', ['tab' => 'attending']) }}" class="text-gray-700 hover:text-emerald-600">🎪 My Events</a></li>
                        </ul>
                    </div>
                </div>
            </aside>

            <main class="min-w-0 space-y-4">
                <x-quick-post-form />

                <nav aria-label="Feed filter" class="rounded-2xl border border-gray-200 bg-white px-4">
                    <ul class="flex items-center gap-5 text-sm">
                        <li><a href="{{ route('feed.index') }}" class="inline-block border-b-2 py-3 {{ $type === null ? 'border-emerald-500 font-semibold text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">All</a></li>
                        <li><a href="{{ route('feed.index', ['type' => 'photo']) }}" class="inline-block border-b-2 py-3 {{ $type === 'photo' ? 'border-emerald-500 font-semibold text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Photos</a></li>
                        <li><a href="{{ route('feed.index', ['type' => 'video']) }}" class="inline-block border-b-2 py-3 {{ $type === 'video' ? 'border-emerald-500 font-semibold text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Videos</a></li>
                        <li><a href="{{ route('feed.index', ['type' => 'text']) }}" class="inline-block border-b-2 py-3 {{ $type === 'text' ? 'border-emerald-500 font-semibold text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Text</a></li>
                    </ul>
                </nav>

                @if ($posts->total() > 0)
                    <p class="text-sm text-gray-500">Showing {{ $posts->firstItem() }}–{{ $posts->lastItem() }} of {{ $posts->total() }} posts</p>
                @endif

                <ul role="feed" aria-label="Your feed" class="space-y-4">
                    @forelse ($posts as $post)
                        <li aria-label="Post by {{ $post->author->name }}">
                            <x-post-card :post="$post" :myReactions="$myReactions" :mySaved="$mySaved" context="feed" />
                        </li>
                    @empty
                        <x-feed-empty-state :user="$user" />
                    @endforelse
                </ul>

                @if ($posts->hasPages())
                    <div class="rounded-2xl border border-gray-200 bg-white p-4">
                        {{ $posts->links() }}
                    </div>
                @endif

                @if ($posts->hasMorePages())
                    <a href="{{ $posts->nextPageUrl() }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Load more posts</a>
                @elseif ($posts->total() > 0)
                    <p class="text-sm text-gray-500">You're all caught up! 🎉</p>
                @endif
            </main>

            <aside class="hidden lg:block">
                <div class="sticky top-20 space-y-4">
                    <x-widget-who-to-follow :suggestions="$suggestions" />
                    <x-widget-trending-hashtags :hashtags="$trending" />
                    <x-widget-upcoming-events :events="$events" />
                    @if ($contest)
                        <x-widget-active-contests :contest="$contest" />
                    @endif
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
