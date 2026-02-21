<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Saved Posts</h2>
            <a href="{{ route('feed.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to Feed</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            @forelse ($posts as $post)
                @include('posts.partials.card', ['post' => $post])
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-600">
                    You have no saved posts yet.
                </div>
            @endforelse

            <div>
                {{ $posts->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
