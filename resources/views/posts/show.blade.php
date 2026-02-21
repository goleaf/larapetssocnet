<x-app-layout>
    <div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <x-post-card :post="$post" />

        @php
            $taggedPets = collect($post->tagged_pets ?? [])
                ->filter()
                ->whenNotEmpty(fn ($ids) => auth()->user()?->pets()->whereIn('id', $ids)->get(), fn () => collect());
        @endphp

        @if ($taggedPets->isNotEmpty())
            <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4">
                <h3 class="mb-2 text-sm font-semibold text-gray-800">Tagged Pets</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach ($taggedPets as $pet)
                        <a href="{{ route('pets.show', $pet->slug ?? $pet->getKey()) }}" class="rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-700 hover:bg-emerald-100">
                            {{ $pet->name }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Comments Section -->
        <div class="mt-8 bg-white rounded-lg shadow p-6" id="comments">
            <h3 class="text-lg font-medium text-gray-900 mb-6">Comments ({{ $post->comments_count }})</h3>

            @auth
                <!-- Add Top-Level Comment Form -->
                <div class="mb-8 flex space-x-3">
                    <img src="{{ auth()->user()->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) }}"
                        class="w-10 h-10 rounded-full" alt="">
                    <div class="flex-1">
                        <form action="{{ route('posts.comments.store', $post) }}" method="POST">
                            @csrf
                            <textarea name="body" rows="2"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 resize-none"
                                placeholder="Add a comment..." required></textarea>
                            <div class="mt-2 flex justify-end">
                                <button type="submit"
                                    class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-1.5 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    Post Comment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endauth

            <!-- Comments List -->
            @php
                // Usually done in controller, but for simplicity here we query top-level comments and load replies.
                $comments = $post->comments()->topLevel()->with(['user', 'replies.user'])->latest()->get();
            @endphp

            @if($comments->isEmpty())
                <p class="text-gray-500 text-sm text-center py-4">No comments yet. Be the first to share your thoughts!</p>
            @else
                <div class="space-y-4">
                    @foreach($comments as $comment)
                        <x-comment-item :comment="$comment" :post="$post" />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
