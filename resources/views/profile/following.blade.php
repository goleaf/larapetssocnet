<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="shell-title text-xl">Following</h1>
            <p class="mt-1 text-sm shell-text-muted">People {{ $profileUser->name }} follows</p>
        </div>
    </x-slot>

    <section class="shell-card p-5">
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm shell-text-muted">Total: {{ $following->total() }}</p>
            <a href="{{ route('profile.show', ['user' => $profileUser]) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back to Profile</a>
        </div>

        <div class="space-y-3">
            @forelse ($following as $followedUser)
                <article class="flex items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3">
                    @php
                        $followingProfileUrl = filled($followedUser->username) ? route('profile.show', ['user' => $followedUser]) : null;
                    @endphp
                    <a href="{{ $followingProfileUrl ?? '#' }}" class="flex min-w-0 items-center gap-3 {{ $followingProfileUrl ? '' : 'pointer-events-none opacity-70' }}">
                        <x-avatar :src="$followedUser->getFirstMediaUrl('avatar')" :name="$followedUser->name" size="md" />
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $followedUser->name }}</p>
                            <p class="truncate text-xs shell-text-muted">@{{ $followedUser->username }}</p>
                        </div>
                    </a>
                    <div class="text-right text-xs shell-text-muted">
                        <p>{{ $followedUser->followers_count }} followers</p>
                        <p>{{ $followedUser->following_count }} following</p>
                    </div>
                </article>
            @empty
                <x-empty-state
                    icon="🧭"
                    title="Not following anyone yet"
                    description="Profiles followed by this user will appear here."
                />
            @endforelse
        </div>

        <div class="mt-4">
            {{ $following->links() }}
        </div>
    </section>
</x-app-layout>
