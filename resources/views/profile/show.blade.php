@php
    $avatarUrl = $profileUser->getFirstMediaUrl('avatar');
    $coverUrl = $profileUser->getFirstMediaUrl('cover');
    $tabs = ['posts' => 'Posts', 'pets' => 'Pets', 'photos' => 'Photos', 'likes' => 'Likes'];
    $canInteract = auth()->check() && auth()->id() !== $profileUser->id;
    $isOwner = auth()->check() && auth()->id() === $profileUser->id;
    $location = $profileUser->location ?? $profileUser->city ?? null;

    $previewPets = $canViewContent
        ? ($tab === 'pets' ? $pets->take(8) : $profileUser->pets()->latest()->limit(8)->get())
        : collect();
@endphp

@section('title', at_username($profileUser) . ' — PetSocial')
@php
    $metaDescription = $profileUser->bio ?: ($profileUser->name . "'s profile on PetSocial");
@endphp
@push('meta')
    <meta property="og:type" content="profile">
    <meta property="og:title" content="{{ $profileUser->name }} ({{ at_username($profileUser) }})">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $profileUser->profile_url }}">
    <meta property="profile:username" content="{{ $profileUser->username }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $profileUser->name }} on PetSocial">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $profileUser->profile_url }}">
    @if ($profileUser->is_private)
        <meta name="robots" content="noindex, nofollow">
    @endif
@endpush

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="$profileUser->name" :subtitle="'@' . $profileUser->username" />
    </x-slot>

    <div class="space-y-5" x-data="profileActions({
            isFollowing: @js($isFollowing),
            isBlocked: @js($isBlocked),
            followersCount: @js($profileUser->followers_count),
            followUrl: @js(route('users.follow', ['user' => $profileUser])),
            unfollowUrl: @js(route('users.unfollow', ['user' => $profileUser])),
            blockUrl: @js(route('users.block', ['user' => $profileUser])),
            unblockUrl: @js(route('users.unblock', ['user' => $profileUser]))
        })">
        <x-ui.card padding="0" class="overflow-hidden">
            <div class="relative h-48 w-full sm:h-56">
                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="{{ $profileUser->name }} cover image"
                        class="h-full w-full object-cover" />
                @else
                    <div class="h-full w-full bg-paw-light"></div>
                @endif

                <div class="absolute inset-0 bg-gradient-to-t from-black/45 via-black/15 to-transparent"></div>

                <div class="absolute right-4 top-4 flex items-center gap-2">
                    @if ($profileUser->is_private)
                        <x-ui.badge variant="warning" size="sm" pill>🔒 Private Profile</x-ui.badge>
                    @else
                        <x-ui.badge variant="success" size="sm" pill>🌍 Public Profile</x-ui.badge>
                    @endif

                    @if ($isOwner)
                        <a href="{{ route('settings.profile.edit') }}"
                            class="inline-flex items-center justify-center font-medium transition-all duration-150 rounded-lg text-white bg-black/20 hover:bg-black/40 px-3 py-1.5 text-xs border border-white/30 backdrop-blur-sm shadow-sm"
                            aria-label="Edit your profile">
                            Edit Profile
                        </a>
                    @endif
                </div>
            </div>

            <div class="relative px-5 pb-5 sm:px-6">
                <div class="-mt-12 flex flex-wrap items-end justify-between gap-4">
                    <div class="flex items-end gap-3">
                        <x-ui.avatar :src="$avatarUrl" :name="$profileUser->name" size="2xl"
                            class="h-24 w-24 border-4 border-warm-white shadow-xl bg-warm-white" />
                        <div class="pb-2">
                            <p class="text-2xl font-bold font-display text-bark">{{ $profileUser->name }}</p>
                            <p class="text-sm text-fur">&#64;{{ $profileUser->username }}</p>
                            @if ($location)
                                <p class="text-xs text-fur mt-1">📍 {{ $location }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($isOwner)
                            <x-ui.button href="{{ route('settings.profile.edit') }}" variant="primary" size="sm">Edit
                                Profile</x-ui.button>
                            <x-ui.button href="{{ route('settings.account.edit') }}" variant="ghost" size="sm">Account
                                Settings</x-ui.button>
                        @elseif ($canInteract)
                            <button
                                class="inline-flex items-center justify-center font-medium transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw px-4 py-2 text-sm rounded-lg"
                                :class="isFollowing ? 'bg-warm-white text-bark border border-whisker hover:bg-cream' : 'bg-paw text-white shadow-button hover:bg-paw-dark'"
                                x-bind:disabled="busy || isBlocked" x-bind:aria-pressed="isFollowing.toString()"
                                x-bind:aria-label="isFollowing ? 'Unfollow {{ addslashes($profileUser->name) }}' : 'Follow {{ addslashes($profileUser->name) }}'"
                                @click="toggleFollow">
                                <span x-text="busy ? 'Saving...' : (isFollowing ? 'Following' : 'Follow')"></span>
                            </button>

                            <x-ui.button href="{{ route('messages.conversation', ['peer' => $profileUser]) }}"
                                variant="ghost" size="sm">Message</x-ui.button>

                            @include('profile._actions-dropdown', ['user' => $profileUser, 'isBlocked' => $isBlocked])
                        @elseif (!auth()->check() && Route::has('login'))
                            <x-ui.button href="{{ route('login') }}" variant="primary" size="sm">Sign In to
                                Follow</x-ui.button>
                        @endif
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4" role="list" aria-label="Profile statistics">
                    <a href="{{ route('profile.followers', ['user' => $profileUser]) }}"
                        class="rounded-xl border border-whisker/30 bg-warm-white hover:bg-cream transition-colors px-3 py-2 text-center"
                        aria-label="View followers list">
                        <p class="text-xl font-bold text-bark" x-text="formatCount(followersCount)">
                            {{ number_format((int) $profileUser->followers_count) }}</p>
                        <p class="text-xs text-fur">Followers</p>
                    </a>
                    <a href="{{ route('profile.following', ['user' => $profileUser]) }}"
                        class="rounded-xl border border-whisker/30 bg-warm-white hover:bg-cream transition-colors px-3 py-2 text-center"
                        aria-label="View following list">
                        <p class="text-xl font-bold text-bark">{{ number_format((int) $profileUser->following_count) }}
                        </p>
                        <p class="text-xs text-fur">Following</p>
                    </a>
                    <a href="{{ route('profile.show', ['user' => $profileUser, 'tab' => 'pets']) }}"
                        class="rounded-xl border border-whisker/30 bg-warm-white hover:bg-cream transition-colors px-3 py-2 text-center"
                        aria-label="View pets">
                        <p class="text-xl font-bold text-bark">{{ number_format((int) $profileUser->pets_count) }}</p>
                        <p class="text-xs text-fur">Pets</p>
                    </a>
                    <div class="rounded-xl border border-whisker/30 bg-warm-white px-3 py-2 text-center"
                        aria-label="Privacy status">
                        <p class="text-xl font-bold text-bark">{{ $profileUser->is_private ? 'Private' : 'Public' }}</p>
                        <p class="text-xs text-fur">Visibility</p>
                    </div>
                </div>

                @if ($profileUser->bio)
                    <p class="mt-5 whitespace-pre-line text-sm text-bark">{{ $profileUser->bio }}</p>
                @endif

                @if ($profileUser->website)
                    <a href="{{ $profileUser->website }}" target="_blank" rel="noopener noreferrer"
                        class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-paw hover:text-paw-dark hover:underline"
                        aria-label="Open {{ $profileUser->name }} website">
                        🔗 {{ $profileUser->website }}
                    </a>
                @endif

                <p class="mt-3 text-sm text-fur font-medium" x-show="notice" x-text="notice"></p>
            </div>
        </x-ui.card>

        @if ($canViewContent)
            <x-ui.card>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold font-display text-bark">Pets Strip</h2>
                    <a href="{{ route('profile.show', ['user' => $profileUser, 'tab' => 'pets']) }}"
                        class="text-xs font-semibold text-paw hover:underline focus:outline-none focus:ring-2 focus:ring-paw rounded-sm"
                        aria-label="Open all pets from {{ $profileUser->name }}">
                        See all pets
                    </a>
                </div>

                <div class="-mx-1 flex gap-3 overflow-x-auto px-1 pb-2 no-scrollbar">
                    @forelse ($previewPets as $pet)
                        @php
                            $petRouteParam = $pet->slug ?? $pet->getKey();
                        @endphp
                        <a href="{{ route('pets.show', ['slug' => $petRouteParam]) }}"
                            class="min-w-[11.5rem] rounded-xl border border-whisker/30 bg-warm-white px-3 py-3 transition-all hover:-translate-y-1 hover:shadow-card-hover hover:border-paw/30 focus:outline-none focus:ring-2 focus:ring-paw"
                            aria-label="View pet profile for {{ $pet->name }}">
                            <div class="flex items-center gap-2.5">
                                <x-ui.avatar :src="$pet->getFirstMediaUrl('avatar')" :name="$pet->name" size="sm" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-bark">{{ $pet->name }}</p>
                                    <p class="truncate text-xs text-fur">{{ $pet->species }}</p>
                                </div>
                            </div>
                        </a>
                    @empty
                        <p class="text-sm text-fur">No pets published yet.</p>
                    @endforelse
                </div>
            </x-ui.card>
        @endif

        <x-ui.tabs :tabs="array_map(fn($v, $k) => ['label' => $v, 'value' => $k], array_values($tabs), array_keys($tabs))"
            :active="$tab" class="mb-0" />

        @if (!$canViewContent)
            <x-ui.empty-state icon="🔒" title="This profile is private"
                description="Follow {{ $profileUser->name }} to view posts, pets, photos, and likes.">
                @if ($canInteract)
                    <x-slot name="action">
                        <button
                            class="inline-flex items-center justify-center font-medium transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw px-4 py-2 text-sm rounded-lg shadow-button text-white bg-paw hover:bg-paw-dark mt-2"
                            x-bind:disabled="busy || isBlocked" x-bind:aria-pressed="isFollowing.toString()"
                            x-bind:aria-label="isFollowing ? 'Unfollow {{ addslashes($profileUser->name) }}' : 'Follow {{ addslashes($profileUser->name) }}'"
                            @click="toggleFollow">
                            <span x-text="busy ? 'Saving...' : (isFollowing ? 'Following' : 'Follow to View')"></span>
                        </button>
                    </x-slot>
                @endif
            </x-ui.empty-state>
        @elseif ($tab === 'pets')
            <x-ui.card>
                <div class="mt-1 grid gap-4 sm:grid-cols-2">
                    @forelse ($pets as $pet)
                        @php
                            $petRouteParam = $pet->slug ?? $pet->getKey();
                        @endphp
                        <a href="{{ route('pets.show', ['slug' => $petRouteParam]) }}"
                            class="rounded-xl border border-whisker/30 bg-warm-white px-4 py-4 transition-all hover:-translate-y-1 hover:shadow-card-hover hover:border-paw/30 focus:outline-none focus:ring-2 focus:ring-paw"
                            aria-label="Open {{ $pet->name }} pet profile">
                            <div class="flex items-center gap-3">
                                <x-ui.avatar :src="$pet->getFirstMediaUrl('avatar')" :name="$pet->name" size="md" />
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-bark text-base">{{ $pet->name }}</p>
                                    <p class="truncate text-xs text-fur font-medium mt-0.5">
                                        {{ $pet->species }}{{ $pet->breed ? ' · ' . $pet->breed : '' }}
                                    </p>
                                </div>
                            </div>
                            @if ($pet->bio)
                                <p class="mt-3 line-clamp-2 text-sm text-fur">{{ $pet->bio }}</p>
                            @endif
                        </a>
                    @empty
                        <div class="col-span-full">
                            <x-ui.empty-state icon="🐾" title="No pets yet"
                                description="This user has not added pets to their profile." />
                        </div>
                    @endforelse
                </div>
            </x-ui.card>
        @elseif ($tab === 'photos')
            <x-ui.card>
                <div class="mt-1 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($photos as $photo)
                        <img src="{{ $photo->getUrl() }}" alt="{{ $profileUser->name }} photo"
                            class="h-44 w-full rounded-xl object-cover hover:opacity-90 transition-opacity cursor-pointer shadow-sm" />
                    @empty
                        <div class="col-span-full">
                            <x-ui.empty-state icon="📷" title="No photos yet"
                                description="When this user shares photos, they will appear here." />
                        </div>
                    @endforelse
                </div>
            </x-ui.card>
        @elseif ($tab === 'likes')
            <x-ui.card>
                <x-ui.empty-state icon="❤️" title="No likes to show"
                    description="Likes tab is ready for Wave 2 data integration." />
            </x-ui.card>
        @else
            <section class="space-y-4">
                @forelse ($posts as $post)
                    <x-post-card :post="$post" context="profile" />

                    @if ($isOwner)
                        <div class="-mt-2 flex items-center justify-end gap-2">
                            @if ($post->is_pinned)
                                <form method="POST" action="{{ route('posts.unpin', $post) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="ghost" size="xs">Unpin</x-ui.button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('posts.pin', $post) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="xs">Pin to Profile</x-ui.button>
                                </form>
                            @endif
                        </div>
                    @endif
                @empty
                    <x-ui.empty-state icon="📝" title="No posts yet" description="No posts published yet." />
                @endforelse

                @if ($isOwner && ($privateCount ?? 0) > 0)
                    <div class="mt-6 border-t border-whisker/30 pt-6">
                        <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-fur">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            Private posts
                            <x-ui.badge variant="default" size="sm" pill>{{ $privateCount }}</x-ui.badge>
                        </h3>

                        <div class="space-y-4">
                            @foreach (($privatePosts ?? collect()) as $post)
                                <x-post-card :post="$post" context="profile" />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (method_exists($posts, 'links'))
                    @if($posts->hasPages())
                        <x-ui.card>
                            <x-ui.pagination :paginator="$posts" />
                        </x-ui.card>
                    @endif
                @endif
            </section>
        @endif
    </div>
</x-app-layout>