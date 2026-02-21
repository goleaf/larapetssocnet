@section('title', 'Feed')

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="shell-kicker">Home Feed</p>
                <h1 class="shell-title text-2xl leading-tight">
                    Welcome back,
                    <span class="text-gradient-brand">{{ auth()->user()?->name ? explode(' ', auth()->user()->name)[0] : 'Pet Lover' }}</span>
                </h1>
                <p class="mt-1 text-sm shell-text-muted">Fresh posts from your network, newest first.</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('saved.index') }}" class="btn-base btn-ghost px-3 py-2 text-sm">Saved</a>
                <a href="{{ route('explore.index') }}" class="btn-base btn-secondary px-3 py-2 text-sm">Explore</a>
                <a href="{{ route('posts.create') }}" class="btn-base btn-primary px-3 py-2 text-sm">✚ Create</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-4">
        <section class="shell-panel p-4 sm:p-5">
            <div class="flex items-start gap-3">
                <x-avatar :src="auth()->user()?->avatar_url" :name="auth()->user()?->name" size="md" />
                <div class="min-w-0 flex-1">
                    <p class="shell-title text-sm">Share a pet update</p>
                    <p class="mt-1 text-sm shell-text-muted">Photos, milestones, adoption updates, or quick check-ins.</p>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <a href="{{ route('posts.create') }}" class="btn-base btn-primary px-3 py-2 text-sm">Write Post</a>
                        <a href="{{ route('posts.create') }}" class="btn-base btn-ghost px-3 py-2 text-sm">Add Photos</a>
                        <a href="{{ route('posts.create') }}" class="btn-base btn-ghost px-3 py-2 text-sm">Tag Pet</a>
                    </div>
                </div>
            </div>
        </section>

        @if (session('status'))
            <x-flash-message type="success" :message="session('status')" />
        @endif

        @forelse ($posts as $post)
            @include('posts.partials.card', ['post' => $post])
        @empty
            <x-empty-state
                icon="🧭"
                title="No posts yet"
                description="Follow more users or share your first pet moment to start the feed."
                actionLabel="Create Post"
                :actionHref="route('posts.create')"
            />
        @endforelse

        <div class="shell-card p-3 sm:p-4">
            {{ $posts->links() }}
        </div>
    </div>
</x-app-layout>
