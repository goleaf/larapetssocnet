@props([
    'post',
    'viewer' => null,
    'context' => 'feed',
    'instance' => null,
])

@php
    $author = $post->user ?? $post->author;
    $viewer = $viewer ?? auth()->user();
    $profileUrl = $author ? route('profile.show', $author) : '#';
    $petUrl = $post->pet ? route('pets.show', $post->pet->slug ?? $post->pet->getKey()) : null;
    $displayedAt = $post->published_at ?? $post->created_at;
    $timeLabel = $displayedAt?->diffForHumans();
    $timeIso = $displayedAt?->toIso8601String();
    $timeTitle = $displayedAt?->format('M j, Y g:i A');
    $authorAvatar = $author?->avatar_url;
    $authorName = $author?->name ?? __('a community member');
    $postDomSuffix = filled($instance) ? '-'.\Illuminate\Support\Str::slug((string) $instance) : '';
    $postDomId = 'post-card-'.$post->getKey().$postDomSuffix;
    $postAuthorId = $postDomId.'-author';
    $postBodyId = $postDomId.'-body';
    $mediaAlt = __('Post media shared by :name', ['name' => $authorName]);

    $spatiePhotos = collect($post->getMedia('photos'))->merge($post->getMedia('images'));
    $spatieVideos = collect($post->getMedia('videos'))->merge($post->getMedia('video'));
    $spatieMediaItems = $spatiePhotos->merge($spatieVideos)->values();
    $dbMediaItems = $post->relationLoaded('postMedia') ? $post->postMedia->values() : collect();
    $mediaItems = $dbMediaItems->isNotEmpty() ? $dbMediaItems : $spatieMediaItems;
    $shownMedia = $mediaItems->take(4);
    $hiddenMediaCount = max(0, $mediaItems->count() - $shownMedia->count());

    $isOwner = (int) auth()->id() === (int) $post->user_id;
    $statusValue = $post->status?->value ?? (string) $post->status;
    $isScheduledProfilePost = $context === 'profile' && $isOwner && $statusValue === 'scheduled';
    $likeCount = (int) ($post->likes_count ?? $post->reactions_count ?? 0);
    $isLiked = (bool) ($post->liked_by_viewer ?? false);
    $commentCount = (int) ($post->comments_count ?? 0);
    $isSaved = (bool) ($post->saved_by_viewer ?? false);
    $saveCount = (int) ($post->save_count ?? 0);
    $shareCount = (int) ($post->shares_count ?? 0);
    $postCardState = [
        'authorName' => $authorName,
        'liked' => $isLiked,
        'likes' => $likeCount,
        'saved' => $isSaved,
        'saveCount' => $saveCount,
        'shares' => $shareCount,
        'likeUrl' => route('posts.like', $post),
        'saveUrl' => route('posts.save', $post),
        'shareUrl' => route('posts.share', $post),
        'showUrl' => route('posts.show', $post),
    ];
    $pinIcon = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 17v5" /><path stroke-linecap="round" stroke-linejoin="round" d="M5 17h14l-3.5-4V5.5L17 4V2H7v2l1.5 1.5V13L5 17Z" />';

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

<x-ui.card
    as="article"
    id="{{ $postDomId }}"
    data-ui="post-card"
    data-post-card-instance="{{ $instance ?? 'feed' }}"
    data-post-status="{{ $statusValue }}"
    aria-labelledby="{{ $postAuthorId }}"
    @class([
        'group overflow-hidden ui-card-interactive',
        'border-amber-300 bg-amber-50/80 ring-2 ring-amber-100' => $isScheduledProfilePost,
    ])
    x-data="postCard({{ \Illuminate\Support\Js::from($postCardState) }})"
>
    @if ($context === 'profile' && $post->is_pinned)
        <x-ui.badge
            variant="warning"
            size="sm"
            :icon="$pinIcon"
            data-ui="post-pinned-badge"
            class="mb-3 w-fit"
        >
            Pinned
        </x-ui.badge>
    @endif

    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 flex-1">
            <div class="flex items-start gap-3">
                @if ($author)
                    <a href="{{ $profileUrl }}" class="shrink-0 rounded-[var(--radius-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                        <x-ui.avatar :src="$authorAvatar" :name="$author->name" size="md"/>
                    </a>
                @else
                    <x-ui.avatar :name="'Deleted User'" size="md"/>
                @endif

                <div class="min-w-0">
                    @if ($author)
                        <a id="{{ $postAuthorId }}" href="{{ $profileUrl }}" class="block min-h-6 truncate text-sm font-semibold ui-text hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                            {{ $author->name }}
                        </a>
                        <p class="truncate text-xs shell-text-muted">&#64;{{ $author->username }}</p>
                    @else
                        <p id="{{ $postAuthorId }}" class="text-sm font-semibold ui-text">Deleted User</p>
                    @endif

                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs shell-text-muted">
                        @if ($timeIso && $timeLabel)
                            <time datetime="{{ $timeIso }}" title="{{ $timeTitle }}" class="inline-flex min-h-5 items-center">{{ $timeLabel }}</time>
                        @endif

                        @if ($post->pet && $petUrl)
                            <span aria-hidden="true">•</span>
                            <a href="{{ $petUrl }}" class="rounded-[var(--radius-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
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

        <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
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
        <div id="{{ $postBodyId }}" class="mt-3 whitespace-pre-line text-[0.95rem] leading-7 ui-text">{!! $bodyHtml !!}</div>
    @endif

    @if ($post->relationLoaded('hashtags') && $post->hashtags->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($post->hashtags as $hashtag)
                <a
                    href="{{ route('hashtags.show', $hashtag) }}"
                    class="chip hover-lift min-h-8 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
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
        <p class="mt-3 ui-token">
            <span aria-hidden="true">📍</span>
            <span>{{ $post->location }}</span>
        </p>
    @endif

    @if ($shownMedia->isNotEmpty())
        <div class="mt-4">
            @if ($shownMedia->count() === 1)
                @php($item = $shownMedia->first())
                <div class="ui-media-frame relative">
                    @if ($isVideoMedia($item))
                        <video controls preload="metadata" aria-label="{{ $mediaAlt }}" class="h-72 w-full object-cover sm:h-96">
                            <source src="{{ $mediaUrl($item) }}" type="{{ $item->mime_type ?? 'video/mp4' }}">
                        </video>
                    @else
                        <img src="{{ $mediaUrl($item) }}" alt="{{ $mediaAlt }}" class="h-72 w-full object-cover sm:h-96" loading="lazy">
                    @endif
                </div>
            @elseif ($shownMedia->count() === 2)
                <div class="grid grid-cols-1 gap-2">
                    @foreach ($shownMedia as $item)
                        <div class="ui-media-frame">
                            @if ($isVideoMedia($item))
                                <video controls preload="metadata" aria-label="{{ $mediaAlt }}" class="h-44 w-full object-cover sm:h-56">
                                    <source src="{{ $mediaUrl($item) }}" type="{{ $item->mime_type ?? 'video/mp4' }}">
                                </video>
                            @else
                                <img src="{{ $mediaUrl($item) }}" alt="{{ $mediaAlt }}" class="h-44 w-full object-cover sm:h-56" loading="lazy">
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="grid grid-cols-1 gap-2">
                    @foreach ($shownMedia as $item)
                        <div @class([
                            'ui-media-frame relative',
                            'col-span-2' => $loop->first,
                        ])>
                            @if ($isVideoMedia($item))
                                <video controls preload="metadata" aria-label="{{ $mediaAlt }}" @class([
                                    'w-full object-cover',
                                    'h-52 sm:h-64' => $loop->first,
                                    'h-36 sm:h-44' => ! $loop->first,
                                ])>
                                    <source src="{{ $mediaUrl($item) }}" type="{{ $item->mime_type ?? 'video/mp4' }}">
                                </video>
                            @else
                                <img src="{{ $mediaUrl($item) }}" alt="{{ $mediaAlt }}" @class([
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
        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
            @auth
                <x-ui.button
                    type="button"
                    size="sm"
                    variant="outline"
                    class="min-h-11 w-full sm:w-auto"
                    data-testid="like-toggle"
                    aria-label="{{ __('Like post by :name', ['name' => $authorName]) }}"
                    @click="toggleLike()"
                    x-bind:disabled="likeBusy"
                    x-bind:aria-label="(liked ? 'Unlike post by ' : 'Like post by ') + authorName"
                    x-bind:aria-pressed="liked"
                    x-bind:aria-busy="likeBusy"
                    x-bind:class="liked ? 'border-rose/40 bg-rose-light/60 text-rose' : ''"
                >
                    <span aria-hidden="true" x-text="liked ? '♥' : '♡'"></span>
                    <span x-text="liked ? 'Liked' : 'Like'"></span>
                    <span class="opacity-80" aria-live="polite" x-text="likes"></span>
                </x-ui.button>
            @endauth

            <x-ui.button
                :href="route('posts.show', $post) . '#comments'"
                data-testid="comments-toggle"
                size="sm"
                variant="ghost"
                class="min-h-11 w-full sm:w-auto"
                aria-label="{{ __('Read comments on post by :name', ['name' => $authorName]) }}"
            >
                <span aria-hidden="true">💬</span>
                <span>Comments</span>
                <span class="opacity-80">({{ $commentCount }})</span>
            </x-ui.button>

            @auth
                <x-ui.button
                    type="button"
                    size="sm"
                    variant="ghost"
                    class="min-h-11 w-full sm:w-auto"
                    aria-label="{{ __('Save post by :name', ['name' => $authorName]) }}"
                    @click="toggleSave()"
                    x-bind:disabled="saveBusy"
                    x-bind:aria-label="(saved ? 'Remove saved post by ' : 'Save post by ') + authorName"
                    x-bind:aria-pressed="saved"
                    x-bind:aria-busy="saveBusy"
                    x-bind:class="saved ? 'text-emerald-700' : ''"
                >
                    <span x-text="saved ? 'Saved' : 'Save'"></span>
                    <span class="opacity-80" aria-live="polite" x-text="saveCount"></span>
                </x-ui.button>

                <x-ui.button
                    type="button"
                    size="sm"
                    variant="ghost"
                    class="min-h-11 w-full sm:w-auto"
                    aria-label="{{ __('Copy link to post by :name', ['name' => $authorName]) }}"
                    @click="sharePost()"
                    x-bind:disabled="shareBusy"
                    x-bind:aria-busy="shareBusy"
                >
                    <span x-text="shareCopied ? 'Copied' : 'Share'"></span>
                    <span class="opacity-80" aria-live="polite" x-text="shares"></span>
                </x-ui.button>

                @if (! $isOwner)
                    <form method="POST" action="{{ route('posts.report', $post) }}" class="flex sm:inline-flex" onsubmit="return confirm('Report this post?');">
                        @csrf
                        <input type="hidden" name="reason" value="spam">
                        <x-ui.button type="submit" size="sm" variant="ghost" class="min-h-11 w-full sm:w-auto" aria-label="{{ __('Report post by :name', ['name' => $authorName]) }}">Report</x-ui.button>
                    </form>
                @endif
            @endauth
        </div>
    </div>
</x-ui.card>
