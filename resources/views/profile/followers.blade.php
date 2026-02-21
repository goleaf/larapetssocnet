<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="shell-title text-xl">Followers</h1>
            <p class="mt-1 text-sm shell-text-muted">{{ $profileUser->name }}'s followers</p>
        </div>
    </x-slot>

    <section class="shell-card p-5">
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm shell-text-muted">Total: {{ $followers->total() }}</p>
            <a href="{{ route('profile.show', ['user' => $profileUser]) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back to Profile</a>
        </div>

        <div class="space-y-3">
            @forelse ($followers as $follower)
                <article class="flex items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3">
                    @php
                        $followerProfileUrl = filled($follower->username) ? route('profile.show', ['user' => $follower]) : null;
                    @endphp
                    <a href="{{ $followerProfileUrl ?? '#' }}" class="flex min-w-0 items-center gap-3 {{ $followerProfileUrl ? '' : 'pointer-events-none opacity-70' }}">
                        <x-avatar :src="$follower->getFirstMediaUrl('avatar')" :name="$follower->name" size="md" />
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $follower->name }}</p>
                            <p class="truncate text-xs shell-text-muted">@{{ $follower->username }}</p>
                        </div>
                    </a>
                    <div class="text-right text-xs shell-text-muted">
                        <p>{{ $follower->followers_count }} followers</p>
                        <p>{{ $follower->following_count }} following</p>
                    </div>
                </article>
            @empty
                <x-empty-state
                    icon="🫶"
                    title="No followers yet"
                    description="Followers will appear here as people connect."
                />
            @endforelse
        </div>

        <div class="mt-4">
            {{ $followers->links() }}
        </div>
    </section>
</x-app-layout>
