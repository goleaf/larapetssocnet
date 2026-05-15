<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Saved Posts" description="Your bookmarked posts in one place." icon="🔖">
 <x-slot name="action">
 <a href="{{ route('feed.index') }}"
 class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back
 to Feed</a>
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <div class="py-8">
 <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
 @if (session('status'))
 <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
 {{ session('status') }}
 </div>
 @endif

 @forelse ($savedPosts as $savedPost)
 @if ($savedPost->post)
 <div class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2 text-xs text-gray-600">
 Saved {{ $savedPost->created_at?->diffForHumans() }}
 <form method="POST" action="{{ route('posts.save', $savedPost->post) }}" class="ml-3 inline">
 @csrf
 <button type="submit"
 class="font-semibold text-gray-700 underline hover:text-red-600">Unsave</button>
 </form>
 </div>
 @include('posts.partials.card', ['post'=> $savedPost->post])
 @endif
 @empty
 <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-600">
 You have no saved posts yet.
 </div>
 @endforelse

 <div>
 {{ $savedPosts->links() }}
 </div>
 </div>
 </div>
</x-app-layout>
