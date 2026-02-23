@section('title', '@'.$user->username.' — Followers')

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="shell-title text-xl">Followers</h1>
            <p class="mt-1 text-sm shell-text-muted">&#64;{{ $user->username }} · {{ number_format((int) $user->followers_count) }} followers</p>
        </div>
    </x-slot>

    <section class="shell-card p-5 dark:border-slate-700/60 dark:bg-slate-900/40">
        <form method="GET" class="mb-4">
            <input
                type="search"
                name="q"
                value="{{ request('q') }}"
                placeholder="Search followers..."
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900"
            >
        </form>

        <div class="space-y-2">
            @forelse ($followers as $follower)
                <article data-user-card class="flex items-center gap-3 rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                    <a href="{{ route('profile.show', ['user' => $follower]) }}">
                        <x-avatar :user="$follower" size="md" />
                    </a>

                    <div class="min-w-0 flex-1">
                        <a href="{{ route('profile.show', ['user' => $follower]) }}" class="truncate font-semibold hover:underline">{{ $follower->name }}</a>
                        <p class="text-xs shell-text-muted">&#64;{{ $follower->username }}</p>
                    </div>

                    @auth
                        @if (auth()->id() !== $follower->id)
                            <x-follow-button
                                :user="$follower"
                                :follow-status="auth()->user()->getFollowStatus($follower)"
                                size="sm"
                                :show-remove="auth()->id() === $user->id"
                            />
                        @endif
                    @endauth
                </article>
            @empty
                <x-empty-state icon="users" title="No followers yet" description="Followers will appear here." />
            @endforelse
        </div>

        <div class="mt-4">
            {{ $followers->appends(request()->query())->links() }}
        </div>
    </section>
</x-app-layout>
