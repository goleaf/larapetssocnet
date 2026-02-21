@section('title', 'Following')

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="shell-title text-xl">Following</h1>
            <p class="mt-1 text-sm shell-text-muted">People {{ $profileUser->name }} follows</p>
        </div>
    </x-slot>

    <section class="shell-card p-5 dark:border-slate-700/60 dark:bg-slate-900/40">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm shell-text-muted">Total: {{ number_format($following->total()) }}</p>
            <a href="{{ route('profile.show', ['user' => $profileUser]) }}" class="btn-base btn-ghost px-3 py-2 text-sm" aria-label="Back to {{ $profileUser->name }} profile">
                Back to Profile
            </a>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            @forelse ($following as $followedUser)
                @php
                    $profileUrl = filled($followedUser->username) ? route('profile.show', ['user' => $followedUser]) : null;
                @endphp

                <x-user-card
                    :name="$followedUser->name"
                    :username="$followedUser->username"
                    :avatar="$followedUser->getFirstMediaUrl('avatar')"
                    :followers="$followedUser->followers_count"
                    :following="true"
                    :profile-href="$profileUrl"
                    :action-label="$profileUrl ? 'View Profile' : null"
                    :action-href="$profileUrl"
                />
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
