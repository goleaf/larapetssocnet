@props(['comment', 'post'])

<div class="py-4 {{ $comment->isReply() ? 'ml-8 md:ml-12 border-l-2 border-gray-100 pl-4 mt-2' : 'border-b border-gray-100 last:border-0' }}"
    id="comment-{{ $comment->id }}">
    <div class="flex items-start space-x-3">
        <img src="{{ $comment->user->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($comment->user->name) }}"
            class="w-8 h-8 rounded-full mt-1" alt="">

        <div class="flex-1 min-w-0">
            <div class="bg-gray-50 rounded-lg px-4 py-3">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-sm text-gray-900">{{ $comment->user->name }}</span>
                    <span class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                </div>

                <div class="mt-1 text-sm text-gray-800 break-words">
                    {{ $comment->body }}
                </div>
            </div>

            <!-- Comment Actions -->
            <div class="mt-2 flex items-center space-x-4 text-xs text-gray-500"
                x-data="{ showReply: false, editing: false }">

                <!-- React to Comment -->
                @auth
                    <button @click="
                            fetch('{{ route('comments.react', $comment) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ type: 'like' })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if(data.success) {
                                    // For simplicity, we just reload to show the new count
                                    // In a full SPA this would be reactive
                                    window.location.reload(); 
                                }
                            })
                        " class="font-medium hover:text-indigo-600 transition"
                        :class="{'text-indigo-600': '{{ $comment->reactions_count > 0 ? 'true' : 'false' }}'}">
                        Like {{ $comment->reactions_count > 0 ? '(' . $comment->reactions_count . ')' : '' }}
                    </button>
                @endauth

                <!-- Reply Toggle -->
                @auth
                    @if(!$comment->isReply()) <!-- Only allow 1 level deep -->
                        <button @click="showReply = !showReply"
                            class="font-medium hover:text-gray-900 transition">Reply</button>
                    @endif
                @endauth

                <!-- Admin / Owner Actions -->
                @if(auth()->check() && (auth()->id() === $comment->user_id || auth()->user()->hasRole('admin')))
                    <button @click="editing = !editing" class="hover:text-gray-900 transition">Edit</button>
                    <form method="POST" action="{{ route('posts.comments.destroy', ['post' => $post, 'comment' => $comment]) }}"
                        class="inline" onsubmit="return confirm('Delete comment?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="hover:text-red-600 transition">Delete</button>
                    </form>
                @endif

                <button class="hover:text-gray-900 transition ml-auto">Report</button>

                <!-- Nested Reply Form -->
                <div x-show="showReply" x-cloak class="mt-3 w-full" style="display: none;">
                    <form action="{{ route('posts.comments.store', $post) }}" method="POST">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                        <textarea name="body" rows="2"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            placeholder="Write a reply..." required></textarea>
                        <div class="mt-2 flex justify-end space-x-2">
                            <button type="button" @click="showReply = false"
                                class="text-xs bg-white text-gray-700 hover:bg-gray-50 border border-gray-300 rounded px-3 py-1">Cancel</button>
                            <button type="submit"
                                class="text-xs bg-indigo-600 text-white hover:bg-indigo-700 rounded px-3 py-1">Reply</button>
                        </div>
                    </form>
                </div>

                <!-- Nested Edit Form -->
                @if(auth()->check() && auth()->id() === $comment->user_id)
                    <div x-show="editing" x-cloak class="mt-3 w-full" style="display: none;">
                        <form action="{{ route('posts.comments.update', ['post' => $post, 'comment' => $comment]) }}"
                            method="POST">
                            @csrf @method('PATCH')
                            <textarea name="body" rows="2"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                required>{{ $comment->body }}</textarea>
                            <div class="mt-2 flex justify-end space-x-2">
                                <button type="button" @click="editing = false"
                                    class="text-xs bg-white text-gray-700 hover:bg-gray-50 border border-gray-300 rounded px-3 py-1">Cancel</button>
                                <button type="submit"
                                    class="text-xs bg-indigo-600 text-white hover:bg-indigo-700 rounded px-3 py-1">Save</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>

            <!-- Children / Replies -->
            @if($comment->replies->count() > 0)
                <div class="mt-2">
                    @foreach($comment->replies as $reply)
                        <x-comment-item :comment="$reply" :post="$post" />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
