@props([
    'post',
    'viewer' => null,
    'context' => 'feed',
])

@php
    $author = $post->user ?? $post->author;
    $viewer = $viewer ?? auth()->user();
    $profileUrl = $author ? route('profile.show', $author) : '#';
    $petUrl = $post->pet ? route('pets.show', $post->pet->slug ?? $post->pet->getKey()) : null;
    $displayedAt = $post->published_at ?? $post->created_at;
    $timeLabel = $displayedAt?->diffForHumans();
    $timeIso = $displayedAt?->toIso8601String();
    $authorAvatar = $author?->avatar_url;

    $spatiePhotos = collect($post->getMedia('photos'))->merge($post->getMedia('images'));
    $spatieVideos = collect($post->getMedia('videos'))->merge($post->getMedia('video'));
    $spatieMediaItems = $spatiePhotos->merge($spatieVideos)->values();
    $dbMediaItems = $post->relationLoaded('postMedia') ? $post->postMedia->values() : collect();
    $mediaItems = $dbMediaItems->isNotEmpty() ? $dbMediaItems : $spatieMediaItems;
    $shownMedia = $mediaItems->take(4);
    $hiddenMediaCount = max(0, $mediaItems->count() - $shownMedia->count());

    $isOwner = (int) auth()->id() === (int) $post->user_id;
    $statusValue = $post->status?->value ?? (string) $post->status;
    $likeCount = (int) ($post->likes_count ?? $post->reactions_count ?? 0);
    $isLiked = (bool) ($post->liked_by_viewer ?? false);
    $commentCount = (int) ($post->comments_count ?? 0);
    $isSaved = (bool) ($post->saved_by_viewer ?? false);
    $saveCount = (int) ($post->save_count ?? 0);
    $shareCount = (int) ($post->shares_count ?? 0);

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

    $body = trim((string) $post->body);
    $storedBodyHtml = (string) ($post->body_html ?? '');
    $storedBodyText = trim(html_entity_decode(strip_tags($storedBodyHtml)));
    $bodyHtml = $storedBodyHtml !== '' && ($body === '' || $storedBodyText === $body)
        ? $storedBodyHtml
        : nl2br(e($body));

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
    saved: {{ $isSaved ? 'true' : 'false' }},
    saveCount: {{ $saveCount }},
    saveBusy: false,
    shares: {{ $shareCount }},
    shareBusy: false,
    shareCopied: false,
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
    async toggleSave() {
        if (this.saveBusy) {
            return;
        }

        this.saveBusy = true;
        const previousSaved = this.saved;
        const previousCount = this.saveCount;
        this.saved = !this.saved;
        this.saveCount = Math.max(0, this.saveCount + (this.saved ? 1 : -1));

        try {
            const response = await fetch('{{ route('posts.save', $post) }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
            });

            if (!response.ok) {
                throw new Error('save_request_failed');
            }

            const data = await response.json();

            if (typeof data.saved === 'boolean') {
                this.saved = data.saved;
            }
        } catch {
            this.saved = previousSaved;
            this.saveCount = previousCount;
        } finally {
            this.saveBusy = false;
        }
    },
    async sharePost() {
        if (this.shareBusy) {
            return;
        }

        this.shareBusy = true;
        const previousShares = this.shares;

        try {
            const response = await fetch('{{ route('posts.share', $post) }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
                body: JSON.stringify({ method: 'copy_link' }),
            });

            if (!response.ok) {
                throw new Error('share_request_failed');
            }

            const data = await response.json();

            if (typeof data.shares_count === 'number') {
                this.shares = data.shares_count;
            }

            const shareLink = data.url || '{{ route('posts.show', $post) }}';

            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(shareLink);
            }

            this.shareCopied = true;
            setTimeout(() => this.shareCopied = false, 1500);
        } catch {
            this.shares = previousShares;
        } finally {
            this.shareBusy = false;
        }
    },
}">
    @if ($context === 'profile' && $post->is_pinned)
        <div class="flex items-center gap-2 border-b bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-800" style="border-color: var(--ui-border);">
            📌 Pinned post
        </div>
    @endif

    <header class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <div class="flex items-start gap-3">
                @if ($author)
                    <a href="{{ $profileUrl }}" class="shrink-0">
                        <x-ui.avatar :src="$authorAvatar" :name="$author->name" size="md"/>
                    </a>
                @else
                    <x-ui.avatar :name="'Deleted User'" size="md"/>
                @endif

                <div class="min-w-0">
                    @if ($author)
                        <a href="{{ $profileUrl }}" class="truncate text-sm font-semibold ui-text hover:underline">
                            {{ $author->name }}
                        </a>
                        <p class="truncate text-xs shell-text-muted">&#64;{{ $author->username }}</p>
                    @else
                        <p class="text-sm font-semibold ui-text">Deleted User</p>
                    @endif

                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs shell-text-muted">
                        @if ($timeIso && $timeLabel)
                            <time datetime="{{ $timeIso }}">{{ $timeLabel }}</time>
                        @endif

                        @if ($post->is_pinned)
                            <span aria-hidden="true">•</span>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700">
                                📌 Pinned
                            </span>
                        @endif

                        @if ($post->pet && $petUrl)
                            <span aria-hidden="true">•</span>
                            <a href="{{ $petUrl }}">
                                <x-ui.badge variant="primary" size="sm">🐾 {{ $post->pet->name }}</x-ui.badge>
                            </a>
                        @endif

                        @if ($context === 'profile' && $isOwner)
                            <span aria-hidden="true">•</span>
                            <x-visibility-badge :visibility="$post->visibility"/>
                        @endif

                        @if ($isOwner && $statusValue !== 'published')
                            <span aria-hidden="true">•</span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                {{ ucfirst($statusValue) }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            @if ($author && ! $isOwner)
                <x-follow-button :user="$author" :follow-status="$followStatus ?? 'none'" size="sm"/>
            @endif

            @if ($isOwner)
                <form action="{{ route('posts.destroy', $post) }}" method="POST" onsubmit="return confirm('Delete this post?');">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger" size="xs">Delete</x-ui.button>
                </form>
            @endif
        </div>
    </header>

    @if (filled($bodyHtml))
        <div class="mt-3 whitespace-pre-line text-sm leading-6 ui-text">{!! $bodyHtml !!}</div>
    @endif

    @if ($post->relationLoaded('hashtags') && $post->hashtags->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($post->hashtags as $hashtag)
                <a
                    href="{{ route('hashtags.show', $hashtag) }}"
                    class="chip hover-lift"
                    style="color: var(--ui-primary); border-color: color-mix(in srgb, var(--ui-primary) 36%, var(--ui-border) 64%);"
                >
                    #{{ $hashtag->name }}
                </a>
            @endforeach
        </div>
    @endif

    @if ($isOwner && $statusValue === 'scheduled' && $post->published_at)
        <p class="mt-2 text-xs text-amber-700">
            Scheduled for {{ $post->published_at->format('M j, Y g:i A') }}
        </p>
    @endif

    @if ($post->location)
        <p class="mt-3 inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs shell-text-muted ui-border">
            <span aria-hidden="true">📍</span>
            <span>{{ $post->location }}</span>
        </p>
    @endif

    @if ($shownMedia->isNotEmpty())
        <div class="mt-4">
            @if ($shownMedia->count() === 1)
                @php($item = $shownMedia->first())
                <div class="relative overflow-hidden rounded-[var(--radius-card)] border ui-border">
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
                        <div class="overflow-hidden rounded-[var(--radius-card)] border ui-border">
                            @if ($isVideoMedia($item))
                                <video controls preload="metadata" class="h-44 w-full object-cover sm:h-56">
                                    <source src="{{ $mediaUrl($item) }}" type="{{ $item->mime_type ?? 'video/mp4' }}">
                                </video>
                            @else
                                <img src="{{ $mediaUrl($item) }}" alt="Post media" class="h-44 w-full object-cover sm:h-56" loading="lazy">
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="grid grid-cols-1 gap-2">
                    @foreach ($shownMedia as $item)
                        <div @class([
                            'relative overflow-hidden rounded-[var(--radius-card)] border ui-border',
                            'col-span-2' => $loop->first,
                        ])>
                            @if ($isVideoMedia($item))
                                <video controls preload="metadata" @class([
                                    'w-full object-cover',
                                    'h-52 sm:h-64' => $loop->first,
                                    'h-36 sm:h-44' => ! $loop->first,
                                ])>
                                    <source src="{{ $mediaUrl($item) }}" type="{{ $item->mime_type ?? 'video/mp4' }}">
                                </video>
                            @else
                                <img src="{{ $mediaUrl($item) }}" alt="Post media" @class([
                                    'w-full object-cover',
                                    'h-52 sm:h-64' => $loop->first,
                                    'h-36 sm:h-44' => ! $loop->first,
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

    <div class="mt-4 border-t ui-border pt-3">
        <div class="flex items-center gap-2">
            @auth
                <x-ui.button
                    type="button"
                    size="xs"
                    variant="outline"
                    data-testid="like-toggle"
                    @click="toggleLike()"
                    x-bind:disabled="likeBusy"
                    x-bind:class="liked ? 'border-rose/40 bg-rose-light/60 text-rose' : ''"
                >
                    <span x-text="liked ? '♥' : '♡'"></span>
                    <span x-text="liked ? 'Liked' : 'Like'"></span>
                    <span class="opacity-80" x-text="likes"></span>
                </x-ui.button>
            @endauth

            <x-ui.button
                :href="route('posts.show', $post) . '#comments'"
                data-testid="comments-toggle"
                size="xs"
                variant="ghost"
            >
                <span>💬</span>
                <span>Comments</span>
                <span class="opacity-80">({{ $commentCount }})</span>
            </x-ui.button>

            @auth
                <x-ui.button
                    type="button"
                    size="xs"
                    variant="ghost"
                    @click="toggleSave()"
                    x-bind:disabled="saveBusy"
                    x-bind:class="saved ? 'text-emerald-700' : ''"
                >
                    <span x-text="saved ? 'Saved' : 'Save'"></span>
                    <span class="opacity-80" x-text="saveCount"></span>
                </x-ui.button>

                <x-ui.button
                    type="button"
                    size="xs"
                    variant="ghost"
                    @click="sharePost()"
                    x-bind:disabled="shareBusy"
                >
                    <span x-text="shareCopied ? 'Copied' : 'Share'"></span>
                    <span class="opacity-80" x-text="shares"></span>
                </x-ui.button>

                @if (! $isOwner)
                    <form method="POST" action="{{ route('posts.report', $post) }}" onsubmit="return confirm('Report this post?');">
                        @csrf
                        <input type="hidden" name="reason" value="spam">
                        <x-ui.button type="submit" size="xs" variant="ghost">Report</x-ui.button>
                    </form>
                @endif
            @endauth
        </div>
    </div>
</x-ui.card>
