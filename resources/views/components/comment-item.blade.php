@props(['comment', 'post'])

<div class="group py-2 {{ $comment->isReply() ? 'ml-11 mt-1' : 'mt-4' }}" id="comment-{{ $comment->id }}">
    <div class="flex items-start gap-2">
        <!-- Avatar -->
        <a href="{{ route('profile.show', $comment->user->username) }}" class="shrink-0 mt-0.5">
            <x-ui.avatar :src="$comment->user->avatar_url" :name="$comment->user->name" size="sm" />
        </a>

        <div class="flex-1 min-w-0" x-data="{ showReply: false, editing: false }">

            <!-- Comment Bubble -->
            <div x-show="!editing">
                <div class="inline-block bg-gray-100/80 rounded-2xl px-3.5 py-2.5 max-w-full">
                    <a href="{{ route('profile.show', $comment->user->username) }}"
                        class="font-bold text-sm text-gray-900 hover:underline">
                        {{ $comment->user->name }}
                    </a>
                    <div class="text-sm text-gray-800 whitespace-pre-wrap break-words mt-0.5 leading-snug">
                        {{ $comment->body }}
                    </div>
                </div>

                @if($comment->reactions_count > 0)
                    <div
                        class="inline-flex items-center justify-center bg-white shadow-sm ring-1 ring-gray-900/5 rounded-full px-1.5 py-0.5 -ml-4 -mb-3 z-10 relative">
                        <span class="text-xs">👍</span>
                        <span class="text-[0.65rem] font-medium text-gray-500 ml-0.5">{{ $comment->reactions_count }}</span>
                    </div>
                @endif
            </div>

            <!-- Edit Form -->
            @if(auth()->check() && auth()->id() === $comment->user_id)
                <div x-show="editing" x-cloak class="w-full max-w-2xl bg-gray-50 rounded-2xl p-2 border border-gray-200">
                    <form action="{{ route('posts.comments.update', ['post' => $post, 'comment' => $comment]) }}"
                        method="POST">
                        @csrf @method('PATCH')
                        <textarea name="body" rows="2"
                            class="w-full bg-transparent border-0 focus:ring-0 p-1 text-sm resize-none"
                            required>{{ $comment->body }}</textarea>
                        <div class="mt-2 flex justify-end gap-2 pr-1 pb-1">
                            <button type="button" @click="editing = false"
                                class="text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</button>
                            <button type="submit"
                                class="text-xs font-bold text-paw hover:text-paw-dark drop-shadow-sm">Update</button>
                        </div>
                    </form>
                </div>
            @endif

            <!-- Action Links -->
            <div x-show="!editing" class="flex items-center gap-3 px-3 mt-1 text-xs font-bold text-gray-500">
                @auth
                    <!-- Like Button -->
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
                                            window.location.reload(); 
                                        }
                                    })
                                " class="hover:underline {{ $comment->reactions_count > 0 ? 'text-paw' : '' }}">
                        Like
                    </button>

                    <!-- Reply Button -->
                    @if(!$comment->isReply())
                        <button @click="showReply = !showReply; if(showReply) { $nextTick(() => $refs.replyInput.focus()); }"
                            class="hover:underline">Reply</button>
                    @endif
                @endauth

                <!-- Timestamp -->
                <span class="font-normal text-gray-400 hover:underline cursor-pointer"
                    title="{{ $comment->created_at->format('M j, Y g:i A') }}">
                    {{ str_replace([' seconds', ' minutes', ' hours', ' days', ' weeks', ' months', ' years', ' ago'], ['s', 'm', 'h', 'd', 'w', 'mo', 'y', ''], $comment->created_at->diffForHumans()) }}
                </span>

                <!-- Hover Actions (Edit/Delete/Report) -->
                <div class="opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-2">
                    <span class="text-gray-300 font-normal">&middot;</span>
                    @if(auth()->check() && (auth()->id() === $comment->user_id || auth()->user()->hasRole('admin')))
                        <button @click="editing = true" class="hover:underline hover:text-gray-800">Edit</button>
                        <form method="POST"
                            action="{{ route('posts.comments.destroy', ['post' => $post, 'comment' => $comment]) }}"
                            class="inline m-0 p-0" onsubmit="return confirm('Delete this comment?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="hover:underline hover:text-red-500">Delete</button>
                        </form>
                    @endif
                    @if(auth()->check() && auth()->id() !== $comment->user_id)
                        <form method="POST"
                            action="{{ route('comments.report', ['post' => $post, 'comment' => $comment]) }}"
                            class="inline m-0 p-0" onsubmit="return confirm('Report this comment?');">
                            @csrf
                            <input type="hidden" name="reason" value="spam">
                            <button type="submit" class="hover:underline hover:text-amber-600">Report</button>
                        </form>
                    @endif
                </div>
            </div>

            <!-- Inline Reply Form -->
            <div x-show="showReply" x-cloak class="mt-2 w-full max-w-2xl flex items-start gap-2">
                <x-ui.avatar :src="auth()->user()?->avatar_url" :name="auth()->user()?->name" size="xs" class="mt-1" />
                <div class="flex-1">
                    <form action="{{ route('posts.comments.store', $post) }}" method="POST" class="relative">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                        <textarea x-ref="replyInput" name="body" rows="1"
                            class="w-full py-2 pl-3 pr-10 text-sm bg-gray-100 border-transparent rounded-2xl focus:bg-white focus:border-paw focus:ring-1 focus:ring-paw resize-none overflow-hidden"
                            placeholder="Write a reply..." required
                            oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"
                            @keydown.escape="showReply = false"></textarea>
                        <button type="submit"
                            class="absolute right-2 bottom-1.5 p-1 text-paw hover:bg-paw-light/30 rounded-full transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                class="w-4 h-4">
                                <path
                                    d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Children / Replies -->
            @if($comment->replies->count() > 0)
                <div class="mt-1">
                    @foreach($comment->replies as $reply)
                        <x-comment-item :comment="$reply" :post="$post" />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>