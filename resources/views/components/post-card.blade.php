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

    $mediaItems = $post->mediaItemsForDisplay();
    $shownMedia = $mediaItems->take(4);
    $hiddenMediaCount = max(0, $mediaItems->count() - $shownMedia->count());

    $isOwner = (int) auth()->id() === (int) $post->user_id;
    $showOwnerMenu = $isOwner;
    $editWindowOpen = $post->created_at === null || $post->created_at->greaterThanOrEqualTo(now()->subDay());
    $canEditPost = $isOwner && $editWindowOpen && auth()->user()?->can('update', $post);
    $editedAtTitle = $post->edited_at?->format('M j, Y g:i A');
    $showPinnedProfileBanner = $context === 'profile' && $post->is_pinned && $instance === 'pinned-highlight';
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
        'postId' => (int) $post->getKey(),
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
    $locationLabel = $post->location_display_text ?: $post->location;
    $moodLabel = \App\Support\Posts\PostMood::label($post->mood);
    $moodEmoji = \App\Support\Posts\PostMood::emoji($post->mood);
    $linkPreview = is_array($post->link_preview ?? null) ? $post->link_preview : [];
    $linkPreviewUrl = $linkPreview['url'] ?? null;
    $linkPreviewTitle = $linkPreview['title'] ?? $linkPreview['domain'] ?? $linkPreviewUrl;
    $linkPreviewDescription = $linkPreview['description'] ?? null;
    $linkPreviewImage = $linkPreview['image'] ?? null;
    $linkPreviewDomain = $linkPreview['domain'] ?? ($linkPreviewUrl ? parse_url((string) $linkPreviewUrl, PHP_URL_HOST) : null);
    $quotePost = $post->relationLoaded('quotePost') ? $post->quotePost : null;
    $originalPost = $post->relationLoaded('originalPost') ? $post->originalPost : null;
    $relatedPost = $quotePost ?? $originalPost;
    $relatedPostAuthor = $relatedPost?->user ?? ($relatedPost?->relationLoaded('author') ? $relatedPost?->author : null);

    $isVideoMedia = static fn (mixed $item): bool => $post::mediaItemIsVideo($item);
    $mediaUrl = static fn (mixed $item): string => $post::mediaItemUrl($item);
@endphp

<x-ui.card
    as="article"
    padding="none"
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
    x-bind:class="{ 'ring-2 ring-paw/20': recentlyUpdated }"
>
    @if ($showPinnedProfileBanner)
        <div
            data-ui="post-pinned-banner"
            class="flex min-h-10 items-center gap-2 border-b border-leaf/20 bg-leaf-light px-6 py-2 text-xs font-semibold text-leaf"
        >
            <svg class="h-4 w-4 shrink-0 text-leaf" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                {!! $pinIcon !!}
            </svg>
            <span>Pinned post</span>
        </div>
    @endif

    <div class="p-6">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 flex-1">
            <div class="flex items-start gap-3">
                @if ($author)
                    <a href="{{ $profileUrl }}" class="shrink-0 rounded-[var(--radius-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                        <x-ui.avatar :src="$authorAvatar" :name="$author->name" :user="$author" size="md"/>
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

                        @if ($post->edited_at)
                            <span aria-hidden="true">•</span>
                            <span
                                class="inline-flex min-h-5 items-center text-xs shell-text-muted"
                                title="{{ $editedAtTitle ? 'Edited '.$editedAtTitle : 'Edited' }}"
                                tabindex="0"
                            >
                                Edited
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

            @if ($showOwnerMenu)
                <x-ui.dropdown align="right" width="56" content-classes="py-2">
                    <x-slot name="trigger">
                        <button
                            type="button"
                            data-ui="post-card-owner-menu-trigger"
                            class="icon-button btn-ghost h-[var(--control-height-sm)] w-[var(--control-height-sm)] rounded-none text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
                            aria-label="Post actions"
                            aria-haspopup="menu"
                        >
                            <span aria-hidden="true">•••</span>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        @if ($canEditPost)
                            <livewire:posts.edit-trigger :post="$post" :key="'post-edit-trigger-'.$post->getKey().'-'.$postDomId" />
                        @else
                            <x-ui.dropdown-item type="button" disabled data-ui="post-card-menu-edit-disabled">
                                Cannot edit — posts can only be edited within 24 hours of creation
                            </x-ui.dropdown-item>
                        @endif

                        @if ($context === 'profile')
                            @if ($post->is_pinned)
                                <form method="POST" action="{{ route('posts.unpin', $post) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.dropdown-item type="submit" data-ui="post-card-menu-unpin">
                                        Unpin from profile
                                    </x-ui.dropdown-item>
                                </form>
                            @else
                                <form method="POST" action="{{ route('posts.pin', $post) }}">
                                    @csrf
                                    <x-ui.dropdown-item type="submit" data-ui="post-card-menu-pin">
                                        Pin to profile
                                    </x-ui.dropdown-item>
                                </form>
                            @endif
                        @endif

                        <form action="{{ route('posts.destroy', $post) }}" method="POST" onsubmit="return confirm('Delete this post?');">
                            @csrf
                            @method('DELETE')
                            <x-ui.dropdown-item type="submit" variant="danger" data-ui="post-card-menu-delete">
                                Delete post
                            </x-ui.dropdown-item>
                        </form>
                    </x-slot>
                </x-ui.dropdown>
            @endif
        </div>
    </header>

    @if (filled($bodyHtml))
        <div id="{{ $postBodyId }}" class="mt-3 whitespace-pre-line text-[0.95rem] leading-7 ui-text">{!! $bodyHtml !!}</div>
    @endif

    @if ($relatedPost)
        <a
            href="{{ route('posts.show', $relatedPost) }}"
            class="mt-4 block rounded-[var(--radius-soft)] border ui-border bg-cream/60 p-4 transition hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
        >
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-fur">
                {{ $quotePost ? 'Quote post' : 'Repost' }}
            </p>
            <p class="mt-1 text-sm font-semibold ui-text">
                {{ $relatedPostAuthor?->name ?? __('Community member') }}
            </p>
            @if (filled($relatedPost->body))
                <p class="mt-2 line-clamp-3 text-sm leading-6 shell-text-muted">
                    {{ $relatedPost->body }}
                </p>
            @endif
        </a>
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

    @if ($isOwner && $statusValue === 'scheduled' && ($post->scheduled_publish_at || $post->published_at))
        <p class="mt-2 text-xs text-amber-700">
            Scheduled for {{ ($post->scheduled_publish_at ?? $post->published_at)->format('M j, Y g:i A') }}
        </p>
    @endif

    @if ($locationLabel || $moodLabel)
        <div class="mt-3 flex flex-wrap gap-2">
            @if ($locationLabel)
                <p class="ui-token">
                    <span aria-hidden="true">📍</span>
                    <span>{{ $locationLabel }}</span>
                </p>
            @endif

            @if ($moodLabel)
                <p class="ui-token">
                    <span aria-hidden="true">{{ $moodEmoji }}</span>
                    <span>{{ $moodLabel }}</span>
                </p>
            @endif
        </div>
    @endif

    @if ($linkPreviewUrl)
        <a
            href="{{ $linkPreviewUrl }}"
            target="_blank"
            rel="nofollow noopener noreferrer"
            class="mt-4 block overflow-hidden rounded-[var(--radius-soft)] border ui-border bg-warm-white transition hover:-translate-y-0.5 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
        >
            @if ($linkPreviewImage)
                <img src="{{ $linkPreviewImage }}" alt="" class="max-h-[200px] w-full object-cover" loading="lazy">
            @endif
            <div class="p-4">
                @if ($linkPreviewDomain)
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-fur">{{ $linkPreviewDomain }}</p>
                @endif
                <p class="mt-1 line-clamp-2 text-sm font-semibold ui-text">{{ $linkPreviewTitle }}</p>
                @if ($linkPreviewDescription)
                    <p class="mt-2 line-clamp-2 text-sm leading-6 shell-text-muted">{{ $linkPreviewDescription }}</p>
                @endif
            </div>
        </a>
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
    </div>
</x-ui.card>
