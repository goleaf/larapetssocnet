@section('title', '@'.$user->username.' — Private Profile')

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

<x-app-layout>
    <div class="mx-auto max-w-lg px-4 py-12 text-center">
        <div class="mb-4 flex justify-center">
            <x-avatar :user="$user" size="2xl" />
        </div>

        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $user->name }}</h1>
        <p class="mt-1 text-gray-500 dark:text-gray-400">@{{ $user->username }}</p>

        <div class="mb-6 mt-8">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                <span class="text-2xl" aria-hidden="true">🔒</span>
            </div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">This profile is private</h2>
            <p class="mx-auto mt-2 max-w-xs text-sm text-gray-500 dark:text-gray-400">
                This account is private. Follow <strong>@{{ $user->username }}</strong> to see posts, photos, and pet profiles.
            </p>
        </div>

        @auth
            @php
                $followStatus = auth()->user()->getFollowStatus($user);
            @endphp

            <x-follow-button :user="$user" :follow-status="$followStatus" size="lg" />
        @else
            <a href="{{ route('login') }}" class="inline-block rounded-xl bg-emerald-500 px-8 py-2.5 font-semibold text-white transition-colors hover:bg-emerald-600">
                Log in to follow
            </a>
        @endauth
    </div>
</x-app-layout>
