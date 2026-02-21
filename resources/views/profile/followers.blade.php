@section('title', 'Followers')

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="shell-title text-xl">Followers</h1>
            <p class="mt-1 text-sm shell-text-muted">{{ $profileUser->name }}'s followers</p>
        </div>
    </x-slot>

    <section class="shell-card p-5 dark:border-slate-700/60 dark:bg-slate-900/40">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm shell-text-muted">Total: {{ number_format($followers->total()) }}</p>
            <a href="{{ route('profile.show', ['user' => $profileUser]) }}" class="btn-base btn-ghost px-3 py-2 text-sm" aria-label="Back to {{ $profileUser->name }} profile">
                Back to Profile
            </a>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            @forelse ($followers as $follower)
                @php
                    $profileUrl = filled($follower->username) ? route('profile.show', ['user' => $follower]) : null;
                @endphp

                <x-user-card
                    :name="$follower->name"
                    :username="$follower->username"
                    :avatar="$follower->getFirstMediaUrl('avatar')"
                    :followers="$follower->followers_count"
                    :following="false"
                    :profile-href="$profileUrl"
                    :action-label="$profileUrl ? 'View Profile' : null"
                    :action-href="$profileUrl"
                />
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
