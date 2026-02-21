@props(['post'])

<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    <div class="p-4 flex items-center justify-between border-b border-gray-100">
        <div class="flex items-center space-x-3">
            <img src="{{ $post->author->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($post->author->name) }}"
                class="w-10 h-10 rounded-full" alt="">
            <div>
                <a href="{{ route('profile.show', $post->author->username) }}"
                    class="font-medium text-gray-900 hover:underline">{{ $post->author->name }}</a>
                <div class="text-sm text-gray-500">
                    <a href="{{ route('posts.show', $post) }}"
                        class="hover:underline">{{ $post->created_at->diffForHumans() }}</a>
                    @if($post->location) • <span class="text-gray-400">{{ $post->location }}</span> @endif
                    @if($post->pet) • <span class="text-indigo-500">{{ $post->pet->name }}</span> @endif
                    @if($post->visibility === 'followers') • <span class="text-gray-400">Followers</span> @endif
                    @if($post->visibility === 'private') • <span class="text-gray-400">Private</span> @endif
                </div>
            </div>
        </div>

        @if(auth()->check() && (auth()->id() === $post->user_id || auth()->user()->hasRole('admin')))
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                    </svg>
                </button>
                <div x-show="open" @click.away="open = false"
                    class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10 hidden"
                    :class="{'hidden': !open}">
                    <div class="py-1">
                        @can('update', $post)
                            <a href="{{ route('posts.edit', $post) }}"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit Post</a>
                        @endcan

                        @can('pin', $post)
                            <form method="POST"
                                action="{{ $post->is_pinned ? route('posts.unpin', $post) : route('posts.pin', $post) }}">
                                @csrf
                                @if($post->is_pinned) @method('DELETE') @endif
                                <button type="submit"
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    {{ $post->is_pinned ? 'Unpin Post' : 'Pin Post' }}
                                </button>
                            </form>
                        @endcan

                        @can('delete', $post)
                            <form method="POST" action="{{ route('posts.destroy', $post) }}"
                                onsubmit="return confirm('Are you sure?');">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Delete
                                    Post</button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="p-4 prose max-w-none text-gray-800">
        {!! $post->body_html !!}
    </div>

    @if($post->type === 'photo' && $post->has_media)
        <x-media-grid :photos="$post->photo_urls" />
    @elseif($post->type === 'video' && $post->has_media)
        <x-video-player :url="$post->video_url" />
    @endif

    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
        <div class="flex space-x-6">

            <!-- Reactions Alpine Component -->
            <div x-data="{
                    showPicker: false,
                    reacting: false,
                    currentReaction: '{{ $post->current_user_reaction }}',
                    likesCount: {{ (int) $post->likes_count }},
                    react(type) {
                        if (this.reacting) return;
                        this.reacting = true;
                        this.showPicker = false;
                        
                        fetch('{{ route('posts.react', $post) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ type: type })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.currentReaction = data.data.current_reaction;
                                this.likesCount = data.data.likes_count;
                            }
                        })
                        .finally(() => {
                            this.reacting = false;
                        });
                    }
                }" class="relative flex items-center">

                <!-- Main Button -->
                <button @click="react(currentReaction || 'like')" @mouseenter="showPicker = true"
                    @mouseleave="setTimeout(() => { if(!$el.matches(':hover') && !$refs.picker.matches(':hover')) showPicker = false; }, 300)"
                    class="flex items-center space-x-2 transition font-medium"
                    :class="currentReaction ? 'text-indigo-600' : 'text-gray-500 hover:text-indigo-600'">

                    <template x-if="currentReaction">
                        <span class="mr-1 capitalize" x-text="currentReaction"></span>
                    </template>
                    <template x-if="!currentReaction">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5">
                            </path>
                        </svg>
                    </template>

                    <span x-text="likesCount"></span>
                </button>

                <!-- Floating Picker -->
                <div x-ref="picker" x-show="showPicker" x-transition @mouseenter="showPicker = true"
                    @mouseleave="showPicker = false" style="display: none;"
                    class="absolute bottom-full left-0 mb-2 bg-white rounded-full shadow-lg border border-gray-100 flex items-center px-3 py-2 space-x-3 z-20">

                    @foreach(['like' => '👍', 'love' => '❤️', 'cute' => '🥺', 'funny' => '😂', 'wow' => '😮', 'sad' => '😢', 'support' => '🫂'] as $type => $emoji)
                        <button @click="react('{{ $type }}')" class="hover:scale-125 transition-transform text-xl"
                            title="{{ ucfirst($type) }}">
                            {{ $emoji }}
                        </button>
                    @endforeach
                </div>
            </div>

            <a href="{{ route('posts.show', $post) }}#comments"
                class="flex items-center space-x-2 hover:text-indigo-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                    </path>
                </svg>
                <span>{{ $post->comments_count }}</span>
            </a>
        </div>
        <div>
            @if($post->is_pinned)
                <span
                    class="text-xs font-semibold uppercase tracking-wider text-indigo-500 bg-indigo-50 px-2 py-1 rounded-full">Pinned</span>
            @endif
        </div>
    </div>
</div>