@php
    $author = $post->author ?? $post->user;
    $viewer = $viewer ?? auth()->user();
    $profileUrl = $author ? route('profile.show', $author) : '#';
    $petUrl = $post->pet ? route('pets.show', $post->pet->slug ?? $post->pet->getKey()) : null;
    $timeLabel = $post->created_at?->diffForHumans();
    $timeIso = $post->created_at?->toIso8601String();

    $spatiePhotos = collect($post->getMedia('photos'))->merge($post->getMedia('images'));
    $spatieVideos = collect($post->getMedia('videos'))->merge($post->getMedia('video'));
    $spatieMediaItems = $spatiePhotos->merge($spatieVideos)->values();
    $dbMediaItems = $post->relationLoaded('postMedia') ? $post->postMedia->values() : collect();
    $mediaItems = $dbMediaItems->isNotEmpty() ? $dbMediaItems : $spatieMediaItems;
    $shownMedia = $mediaItems->take(4);
    $hiddenMediaCount = max(0, $mediaItems->count() - $shownMedia->count());

    $comments = $post->relationLoaded('comments')
        ? $post->comments->sortByDesc('created_at')->take(5)->sortBy('created_at')->values()
        : $post->comments()
            ->with('user', 'user.media')
            ->latest()
            ->limit(5)
            ->get()
            ->reverse()
            ->values();

    $isOwner = (int) auth()->id() === (int) $post->user_id;
    $likeCount = (int) ($post->likes_count ?? $post->reactions_count ?? 0);
    $isLiked = false;

    if ($viewer && $post->relationLoaded('reactions')) {
        $isLiked = $post->reactions->where('user_id', $viewer->getKey())->isNotEmpty();
    }

    $followStatus = null;

    if ($viewer && $author && $viewer->getKey() !== $author->getKey()) {
        $followingIds = $viewer->relationLoaded('acceptedFollowing')
            ? $viewer->acceptedFollowing->pluck('id')
            : $viewer->acceptedFollowing()->pluck('users.id');
        $pendingIds = $viewer->relationLoaded('sentPendingRequests')
            ? $viewer->sentPendingRequests->pluck('id')
            : $viewer->sentPendingRequests()->pluck('users.id');

        $followStatus = $followingIds->contains($author->getKey())
            ? 'following'
            : ($pendingIds->contains($author->getKey()) ? 'pending' : 'none');
    }

    $isVideoMedia = static function (mixed $item): bool {
        if (is_object($item) && isset($item->mime_type)) {
            return str_starts_with((string) $item->mime_type, 'video/');
        }

        return is_object($item) && (($item->media_type ?? 'image') === 'video');
    };

    $mediaUrl = static function (mixed $item): string {
        if (is_object($item) && method_exists($item, 'getUrl')) {
            return (string) $item->getUrl();
        }

        if (is_object($item) && method_exists($item, 'url')) {
            return (string) $item->url();
        }

        return '';
    };
@endphp

<x-ui.card class="overflow-hidden" x-data="{
        liked: {{ $isLiked ? 'true' : 'false' }},
        likes: {{ $likeCount }},
        likeBusy: false,
        commentsOpen: false,
        async toggleLike() {
            if (this.likeBusy) {
                return;
            }

            this.likeBusy = true;
            const previousLiked = this.liked;
            const previousLikes = this.likes;
            this.liked = !this.liked;
            this.likes = Math.max(0, this.likes + (this.liked ? 1 : -1));

            try {
                const response = await fetch('{{ route('posts.like', $post) }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                });

                if (!response.ok) {
                    throw new Error('like_request_failed');
                }

                const data = await response.json();

                if (typeof data.count === 'number') {
                    this.likes = data.count;
                } else if (typeof data.likes_count === 'number') {
                    this.likes = data.likes_count;
                } else if (typeof data.data?.likes_count === 'number') {
                    this.likes = data.data.likes_count;
                }

                if (typeof data.liked === 'boolean') {
                    this.liked = data.liked;
                } else if (typeof data.action === 'string') {
                    this.liked = data.action !== 'removed';
                } else if (typeof data.data?.current_reaction === 'string') {
                    this.liked = data.data.current_reaction !== '';
                }
            } catch {
                this.liked = previousLiked;
                this.likes = previousLikes;
            } finally {
                this.likeBusy = false;
            }
        },
    }">
    <header class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <div class="flex items-start gap-3">
                @if ($author)
                    <a href="{{ $profileUrl }}" class="shrink-0">
                        <x-ui.avatar :src="$author->avatar_url" :name="$author->name" size="md" />
                    </a>
                @else
                    <x-ui.avatar :name="'Deleted User'" size="md" />
                @endif

                <div class="min-w-0">
                    @if ($author)
                        <a href="{{ $profileUrl }}" class="truncate text-sm font-semibold hover:underline"
                            style="color: var(--ui-text);">
                            {{ $author->name }}
                        </a>
                        <p class="truncate text-xs shell-text-muted">&#64;{{ $author->username }}</p>
                    @else
                        <p class="text-sm font-semibold" style="color: var(--ui-text);">Deleted User</p>
                    @endif

                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs shell-text-muted">
                        @if ($timeIso && $timeLabel)
                            <time datetime="{{ $timeIso }}">{{ $timeLabel }}</time>
                        @endif

                        @if ($post->pet && $petUrl)
                            <span aria-hidden="true">•</span>
                            <a href="{{ $petUrl }}">
                                <x-ui.badge variant="primary" size="sm">🐾 {{ $post->pet->name }}</x-ui.badge>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            @if ($author && !$isOwner)
                @include('partials.follow-button', ['user' => $author, 'status' => $followStatus])
            @endif

            @if ($isOwner)
                <form action="{{ route('posts.destroy', $post) }}" method="POST"
                    onsubmit="return confirm('Delete this post?');">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger" size="xs">Delete</x-ui.button>
                </form>
            @endif
        </div>
    </header>

    @if (filled($post->body))
        <p class="mt-3 whitespace-pre-line text-sm leading-6" style="color: var(--ui-text);">{{ $post->body }}</p>
    @endif

    @if ($shownMedia->isNotEmpty())
    <div class="mt-4">
        @if ($shownMedia->count() === 1)
        @php($item = $shownMedia->first())
            <div class="relative overflow-hidden rounded-xl border" style="border-color: var(--ui-border);">
                @if ($isVideoMedia($item))
                    <video controls preload="metadata" class="h-72 w-full object-cover sm:h-96">
                        <source src="{{ $mediaUrl($item) }}" type="{{ $item->mime_type ?? 'video/mp4' }}">
                    </video>
                @else
                    <img src="{{ $mediaUrl($item) }}" alt="Post media" class="h-72 w-full object-cover sm:h-96" loading="lazy">
                @endif
            </div>
        @elseif ($shownMedia->count() === 2)
            <div class="grid grid-cols-1 gap-2">
                @foreach ($shownMedia as $item)
                    <div class="overflow-hidden rounded-xl border" style="border-color: var(--ui-border);">
                        @if ($isVideoMedia($item))
                            <video controls preload="metadata" class="h-44 w-full object-cover sm:h-56">
                                <source src="{{ $mediaUrl($item) }}" type="{{ $item->mime_type ?? 'video/mp4' }}">
                            </video>
                        @else
                            <img src="{{ $mediaUrl($item) }}" alt="Post media" class="h-44 w-full object-cover sm:h-56"
                                loading="lazy">
                        @endif
                    </div>
                @endforeach
            </div>
        @else
        <div class="grid grid-cols-1 gap-2">
            @foreach ($shownMedia as $item)
                <div @class([
                    'relative overflow-hidden rounded-xl border',
                    'col-span-2' => $loop->first,
                ])
                    style="border-color: var(--ui-border);">
                    @if ($isVideoMedia($item))
                        <video controls preload="metadata" @class([
                            'w-full object-cover',
                            'h-52 sm:h-64' => $loop->first,
                            'h-36 sm:h-44' => !$loop->first,
                        ])>
                            <source src="{{ $mediaUrl($item) }}" type="{{ $item->mime_type ?? 'video/mp4' }}">
                        </video>
                    @else
                        <img src="{{ $mediaUrl($item) }}" alt="Post media" @class([
                            'w-full object-cover',
                            'h-52 sm:h-64' => $loop->first,
                            'h-36 sm:h-44' => !$loop->first,
                        ]) loading="lazy">
                    @endif

                    @if ($loop->last && $hiddenMediaCount > 0)
                        <div class="absolute inset-0 flex items-center justify-center bg-black/45 text-xl font-bold text-white">
                            +{{ $hiddenMediaCount }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    <div class="mt-4 border-t pt-3" style="border-color: var(--ui-border);">
        <div class="flex items-center gap-2">
            <button type="button" @click="toggleLike()" :disabled="likeBusy" data-testid="like-toggle"
                class="inline-flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                :class="liked
                    ? 'border-rose-200 bg-rose-50 text-rose-600'
                    : 'border-[color:var(--ui-border)] bg-[color:var(--ui-surface)] text-[color:var(--ui-text)] hover:bg-[color:var(--ui-surface-muted)]'">
                <span x-text="liked ? '♥' : '♡'"></span>
                <span x-text="liked ? 'Liked' : 'Like'"></span>
                <span class="opacity-80" x-text="likes"></span>
            </button>

            <button type="button" @click="commentsOpen = !commentsOpen" data-testid="comments-toggle"
                class="inline-flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors hover:bg-[color:var(--ui-surface-muted)]"
                style="border-color: var(--ui-border); color: var(--ui-text);">
                <span>💬</span>
                <span>Comments</span>
                <span class="opacity-80">({{ (int) ($post->comments_count ?? $comments->count()) }})</span>
            </button>

            <button type="button"
                class="inline-flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors hover:bg-[color:var(--ui-surface-muted)]"
                style="border-color: var(--ui-border); color: var(--ui-text);" x-data="{ copied: false }" @click="
                    const shareLink = '{{ route('posts.show', $post) }}';
                    navigator.clipboard?.writeText(shareLink)
                        .then(() => {
                            copied = true;
                            setTimeout(() => copied = false, 1500);
                        });
                ">
                <span x-text="copied ? 'Copied' : 'Share'"></span>
            </button>
        </div>

        <div x-show="commentsOpen" x-transition.opacity class="mt-3 space-y-3" style="display: none;">
            <div class="space-y-2">
                @forelse ($comments as $comment)
                    <div class="rounded-xl border p-3"
                        style="border-color: var(--ui-border); background: color-mix(in srgb, var(--ui-surface-muted) 72%, white 28%);">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold" style="color: var(--ui-text);">
                                    {{ $comment->user?->name ?? 'Unknown user' }}
                                    <span class="font-normal shell-text-muted">
                                        @if ($comment->created_at)
                                            · {{ $comment->created_at->diffForHumans() }}
                                        @endif
                                    </span>
                                </p>
                                <p class="mt-1 text-sm break-words" style="color: var(--ui-text);">{{ $comment->body }}</p>
                            </div>

                            @if (auth()->id() === $comment->user_id)
                                <form action="{{ route('comments.destroy', $comment) }}" method="POST"
                                    onsubmit="return confirm('Delete this comment?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" data-testid="comment-delete" variant="danger"
                                        size="xs">Delete</x-ui.button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-xs shell-text-muted">No comments yet. Be the first to comment.</p>
                @endforelse
            </div>

            @auth
                <form action="{{ route('posts.comments.store', $post) }}" method="POST" class="space-y-2">
                    @csrf
                    <label for="comment-body-{{ $post->id }}" class="sr-only">Add comment</label>
                    <textarea id="comment-body-{{ $post->id }}" name="body" data-testid="comment-body" rows="2" required
                        class="form-textarea" placeholder="Add a comment..."></textarea>
                    <div class="flex justify-end">
                        <x-ui.button type="submit" data-testid="comment-submit" variant="primary"
                            size="xs">Comment</x-ui.button>
                    </div>
                </form>
            @else
                <x-ui.button href="{{ route('login') }}" variant="ghost" size="xs">Log in to comment</x-ui.button>
            @endauth
        </div>
    </div>
</x-ui.card>