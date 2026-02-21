<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Post #{{ $post->id }} - {{ config('app.name', 'LaraPets') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('explore.index') }}" class="text-lg font-semibold text-gray-900">{{ config('app.name', 'LaraPets') }}</a>

            <nav class="flex items-center gap-2 text-sm">
                <a href="{{ route('explore.index') }}" class="rounded-lg px-3 py-2 hover:bg-gray-100">Explore</a>
                @auth
                    <a href="{{ route('feed.index') }}" class="rounded-lg px-3 py-2 hover:bg-gray-100">Feed</a>
                    <a href="{{ route('saved.index') }}" class="rounded-lg px-3 py-2 hover:bg-gray-100">Saved</a>
                    <a href="{{ route('posts.create') }}" class="rounded-lg bg-blue-600 px-3 py-2 font-semibold text-white hover:bg-blue-700">New Post</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 hover:bg-gray-100">Login</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="py-8">
        <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('posts.partials.card', ['post' => $post])

            @if ($taggedPets->isNotEmpty())
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Tagged Pets</h3>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($taggedPets as $pet)
                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">{{ $pet->name }}</span>
                        @endforeach
                    </div>
                </section>
            @endif

            @auth
                @php
                    $commentReactionTypes = ['love' => '❤️', 'cute' => '🥹', 'funny' => '😂', 'wow' => '😮', 'sad' => '😢', 'support' => '🤝'];
                @endphp
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">React</h3>
                    <p id="reaction-status" class="mt-1 text-xs text-gray-500">Current reaction: {{ $userReaction ? ucfirst($userReaction) : 'None' }}</p>
                    <p id="likes-count" class="mt-1 text-xs text-gray-500">Total reactions: {{ $post->likes_count }}</p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (\App\Models\PostReaction::TYPES as $type)
                            <button
                                type="button"
                                class="js-react-btn rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                data-type="{{ $type }}"
                            >
                                {{ ucfirst($type) }}
                                @if ($userReaction === $type)
                                    ✓
                                @endif
                            </button>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('posts.save.toggle', $post) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                {{ $isSaved ? 'Unsave' : 'Save' }}
                            </button>
                        </form>

                        @if (auth()->id() === $post->user_id)
                            <a href="{{ route('posts.edit', $post) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Edit</a>

                            <form method="POST" action="{{ route('posts.destroy', $post) }}" onsubmit="return confirm('Delete this post?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Delete</button>
                            </form>

                            @if ($post->is_pinned)
                                <form method="POST" action="{{ route('posts.unpin', $post) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-amber-300 px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50">Unpin</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('posts.pin', $post) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-amber-300 px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50">Pin to Profile</button>
                                </form>
                            @endif
                        @else
                            <form method="POST" action="{{ route('posts.report', $post) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <select name="reason" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    <option value="spam">Spam</option>
                                    <option value="abuse">Abusive content</option>
                                    <option value="misinformation">Misinformation</option>
                                    <option value="other">Other</option>
                                </select>
                                <input type="text" name="details" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Optional details">
                                <button type="submit" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Report</button>
                            </form>
                        @endif
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Comments</h3>

                    <form method="POST" action="{{ route('posts.comments.store', $post) }}" class="mt-3 space-y-2">
                        @csrf
                        <textarea name="body" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Write a comment..." required></textarea>
                        <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Comment</button>
                    </form>

                    <div class="mt-4 space-y-4">
                        @forelse ($post->topLevelComments as $comment)
                            <article class="rounded-lg border border-gray-200 p-3">
                                <p class="text-sm font-semibold text-gray-900">{{ $comment->user->name }}</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-gray-800">{{ $comment->body }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $comment->created_at?->diffForHumans() }}</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <span id="comment-reactions-count-{{ $comment->id }}">{{ (int) $comment->reactions_count }}</span> reactions
                                </p>

                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($commentReactionTypes as $type => $emoji)
                                        <button
                                            type="button"
                                            class="js-comment-react rounded-lg border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                            data-url="{{ route('posts.comments.react', [$post, $comment]) }}"
                                            data-type="{{ $type }}"
                                            data-comment-id="{{ $comment->id }}"
                                        >
                                            {{ $emoji }} {{ ucfirst($type) }}
                                        </button>
                                    @endforeach
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    @if ($comment->user_id === auth()->id())
                                        <form method="POST" action="{{ route('posts.comments.update', [$post, $comment]) }}" class="flex flex-wrap items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="body" value="{{ $comment->body }}" class="rounded-lg border border-gray-300 px-2 py-1 text-xs">
                                            <button type="submit" class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">Update</button>
                                        </form>

                                        <form method="POST" action="{{ route('posts.comments.destroy', [$post, $comment]) }}" onsubmit="return confirm('Delete this comment?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-red-300 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50">Delete</button>
                                        </form>
                                    @endif
                                </div>

                                <form method="POST" action="{{ route('posts.comments.store', $post) }}" class="mt-3 space-y-2">
                                    @csrf
                                    <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                    <textarea name="body" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs" placeholder="Write a reply..." required></textarea>
                                    <button type="submit" class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">Reply</button>
                                </form>

                                @if ($comment->replies->isNotEmpty())
                                    <div class="mt-3 space-y-2 border-l border-gray-200 pl-3">
                                        @foreach ($comment->replies as $reply)
                                            <article class="rounded-lg bg-gray-50 p-2">
                                                <p class="text-xs font-semibold text-gray-900">{{ $reply->user->name }}</p>
                                                <p class="mt-1 whitespace-pre-line text-xs text-gray-800">{{ $reply->body }}</p>
                                                <p class="mt-1 text-[11px] text-gray-500">{{ $reply->created_at?->diffForHumans() }}</p>
                                                <p class="mt-1 text-[11px] text-gray-500">
                                                    <span id="comment-reactions-count-{{ $reply->id }}">{{ (int) $reply->reactions_count }}</span> reactions
                                                </p>

                                                <div class="mt-2 flex flex-wrap gap-1">
                                                    @foreach ($commentReactionTypes as $type => $emoji)
                                                        <button
                                                            type="button"
                                                            class="js-comment-react rounded-lg border border-gray-300 px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50"
                                                            data-url="{{ route('posts.comments.react', [$post, $reply]) }}"
                                                            data-type="{{ $type }}"
                                                            data-comment-id="{{ $reply->id }}"
                                                        >
                                                            {{ $emoji }}
                                                        </button>
                                                    @endforeach
                                                </div>

                                                @if ($reply->user_id === auth()->id())
                                                    <div class="mt-2 flex flex-wrap gap-2">
                                                        <form method="POST" action="{{ route('posts.comments.update', [$post, $reply]) }}" class="flex flex-wrap items-center gap-2">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="text" name="body" value="{{ $reply->body }}" class="rounded-lg border border-gray-300 px-2 py-1 text-[11px]">
                                                            <button type="submit" class="rounded-lg border border-gray-300 px-2 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-50">Update</button>
                                                        </form>

                                                        <form method="POST" action="{{ route('posts.comments.destroy', [$post, $reply]) }}" onsubmit="return confirm('Delete this reply?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="rounded-lg border border-red-300 px-2 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-50">Delete</button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </article>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p class="text-sm text-gray-600">No comments yet.</p>
                        @endforelse
                    </div>
                </section>
            @else
                <section class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm">
                    <a href="{{ route('login') }}" class="font-semibold text-blue-600 hover:text-blue-700">Log in</a>
                    to react, comment, save, pin, or report posts.
                </section>
            @endauth
        </div>
    </main>

    @auth
        <script>
            (() => {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const statusEl = document.getElementById('reaction-status');
                const likesEl = document.getElementById('likes-count');
                const buttons = document.querySelectorAll('.js-react-btn');

                buttons.forEach((button) => {
                    button.addEventListener('click', async () => {
                        const type = button.dataset.type;

                        try {
                            const response = await fetch(@json(route('posts.react', $post)), {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': token,
                                },
                                body: JSON.stringify({ type }),
                            });

                            if (!response.ok) {
                                throw new Error('Reaction request failed');
                            }

                            const payload = await response.json();
                            if (payload?.success) {
                                const current = payload?.data?.current_reaction;
                                const likes = payload?.data?.likes_count;

                                statusEl.textContent = `Current reaction: ${current ? current.charAt(0).toUpperCase() + current.slice(1) : 'None'}`;
                                likesEl.textContent = `Total reactions: ${likes}`;

                                buttons.forEach((btn) => {
                                    const btnType = btn.dataset.type;
                                    const label = btnType.charAt(0).toUpperCase() + btnType.slice(1);
                                    btn.textContent = current === btnType ? `${label} ✓` : label;
                                });
                            }
                        } catch (error) {
                            statusEl.textContent = 'Could not update reaction right now.';
                        }
                    });
                });

                const commentButtons = document.querySelectorAll('.js-comment-react');
                commentButtons.forEach((button) => {
                    button.addEventListener('click', async () => {
                        const type = button.dataset.type;
                        const url = button.dataset.url;
                        const commentId = button.dataset.commentId;

                        try {
                            const response = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': token,
                                },
                                body: JSON.stringify({ type }),
                            });

                            if (!response.ok) {
                                throw new Error('Comment reaction request failed');
                            }

                            const payload = await response.json();
                            if (payload?.success) {
                                const countEl = document.getElementById(`comment-reactions-count-${commentId}`);
                                if (countEl) {
                                    countEl.textContent = String(payload.data.reactions_count ?? 0);
                                }
                            }
                        } catch (error) {
                            // no-op
                        }
                    });
                });
            })();
        </script>
    @endauth
</body>
</html>
