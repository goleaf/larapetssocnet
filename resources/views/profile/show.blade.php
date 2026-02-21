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

@section('title', at_username($profileUser).' — PetSocial')
@php
    $metaDescription = $profileUser->bio ?: ($profileUser->name."'s profile on PetSocial");
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
        <div>
            <h1 class="shell-title text-xl">{{ $profileUser->name }}</h1>
            <p class="mt-1 text-sm shell-text-muted">@{{ $profileUser->username }}</p>
        </div>
    </x-slot>

    <div
        class="space-y-5"
        x-data="profileActions({
            isFollowing: @js($isFollowing),
            isBlocked: @js($isBlocked),
            followersCount: @js($profileUser->followers_count),
            followUrl: @js(route('users.follow', ['user' => $profileUser])),
            unfollowUrl: @js(route('users.unfollow', ['user' => $profileUser])),
            blockUrl: @js(route('users.block', ['user' => $profileUser])),
            unblockUrl: @js(route('users.unblock', ['user' => $profileUser]))
        })"
    >
        <section class="shell-card overflow-hidden dark:border-slate-700/60 dark:bg-slate-900/40">
            <div class="relative h-48 w-full sm:h-56">
                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="{{ $profileUser->name }} cover image" class="h-full w-full object-cover" />
                @else
                    <div
                        class="h-full w-full"
                        style="background: linear-gradient(130deg, color-mix(in srgb, var(--ui-primary) 26%, var(--ui-surface) 74%), color-mix(in srgb, var(--ui-accent) 22%, var(--ui-surface) 78%));"
                    ></div>
                @endif

                <div class="absolute inset-0 bg-gradient-to-t from-black/45 via-black/15 to-transparent"></div>

                <div class="absolute right-4 top-4 flex items-center gap-2">
                    @if ($profileUser->is_private)
                        <span class="chip border-white/40 bg-black/30 text-white">🔒 Private Profile</span>
                    @else
                        <span class="chip border-white/40 bg-black/30 text-white">🌍 Public Profile</span>
                    @endif

                    @if ($isOwner)
                        <a href="{{ route('settings.profile.edit') }}" class="btn-base btn-ghost border-white/35 bg-black/20 px-3 py-2 text-xs text-white hover:bg-black/30" aria-label="Edit your profile">
                            Edit Profile
                        </a>
                    @endif
                </div>
            </div>

            <div class="relative px-5 pb-5 sm:px-6">
                <div class="-mt-12 flex flex-wrap items-end justify-between gap-4">
                    <div class="flex items-end gap-3">
                        <x-avatar :src="$avatarUrl" :name="$profileUser->name" size="2xl" class="h-24 w-24 border-4 shadow-xl" />
                        <div class="pb-2">
                            <p class="shell-title text-2xl">{{ $profileUser->name }}</p>
                            <p class="text-sm shell-text-muted">@{{ $profileUser->username }}</p>
                            @if ($location)
                                <p class="text-xs shell-text-muted">📍 {{ $location }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($isOwner)
                            <a href="{{ route('settings.profile.edit') }}" class="btn-base btn-primary px-4 py-2 text-sm" aria-label="Open profile settings">
                                Edit Profile
                            </a>
                            <a href="{{ route('settings.account.edit') }}" class="btn-base btn-ghost px-4 py-2 text-sm" aria-label="Open account settings">
                                Account Settings
                            </a>
                        @elseif ($canInteract)
                            <x-follow-button
                                variant="primary"
                                size="md"
                                class="px-4 py-2"
                                x-bind:disabled="busy || isBlocked"
                                x-bind:aria-pressed="isFollowing.toString()"
                                x-bind:aria-label="isFollowing ? 'Unfollow {{ addslashes($profileUser->name) }}' : 'Follow {{ addslashes($profileUser->name) }}'"
                                @click="toggleFollow"
                            >
                                <span x-text="busy ? 'Saving...' : (isFollowing ? 'Following' : 'Follow')"></span>
                            </x-follow-button>

                            <a href="{{ route('messages.conversation', ['peer' => $profileUser]) }}" class="btn-base btn-ghost px-4 py-2 text-sm" aria-label="Send a message to {{ $profileUser->name }}">
                                Message
                            </a>
                            @include('profile._actions-dropdown', ['user' => $profileUser, 'isBlocked' => $isBlocked])
                        @elseif (! auth()->check() && Route::has('login'))
                            <a href="{{ route('login') }}" class="btn-base btn-primary px-4 py-2 text-sm" aria-label="Sign in to follow {{ $profileUser->name }}">
                                Sign In to Follow
                            </a>
                        @endif
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4" role="list" aria-label="Profile statistics">
                    <a href="{{ route('profile.followers', ['user' => $profileUser]) }}" class="rounded-xl border border-[var(--ui-border)] px-3 py-2 text-center dark:border-slate-700/60 dark:bg-slate-900/30" aria-label="View followers list">
                        <p class="text-xl font-bold" x-text="formatCount(followersCount)">{{ number_format((int) $profileUser->followers_count) }}</p>
                        <p class="text-xs shell-text-muted">Followers</p>
                    </a>
                    <a href="{{ route('profile.following', ['user' => $profileUser]) }}" class="rounded-xl border border-[var(--ui-border)] px-3 py-2 text-center dark:border-slate-700/60 dark:bg-slate-900/30" aria-label="View following list">
                        <p class="text-xl font-bold">{{ number_format((int) $profileUser->following_count) }}</p>
                        <p class="text-xs shell-text-muted">Following</p>
                    </a>
                    <a href="{{ route('profile.show', ['user' => $profileUser, 'tab' => 'pets']) }}" class="rounded-xl border border-[var(--ui-border)] px-3 py-2 text-center dark:border-slate-700/60 dark:bg-slate-900/30" aria-label="View pets">
                        <p class="text-xl font-bold">{{ number_format((int) $profileUser->pets_count) }}</p>
                        <p class="text-xs shell-text-muted">Pets</p>
                    </a>
                    <div class="rounded-xl border border-[var(--ui-border)] px-3 py-2 text-center dark:border-slate-700/60 dark:bg-slate-900/30" aria-label="Privacy status">
                        <p class="text-xl font-bold">{{ $profileUser->is_private ? 'Private' : 'Public' }}</p>
                        <p class="text-xs shell-text-muted">Visibility</p>
                    </div>
                </div>

                @if ($profileUser->bio)
                    <p class="mt-4 whitespace-pre-line text-sm">{{ $profileUser->bio }}</p>
                @endif

                @if ($profileUser->website)
                    <a
                        href="{{ $profileUser->website }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-3 inline-flex items-center gap-1 text-sm font-semibold hover:underline"
                        style="color: var(--ui-primary);"
                        aria-label="Open {{ $profileUser->name }} website"
                    >
                        🔗 {{ $profileUser->website }}
                    </a>
                @endif

                <p class="mt-3 text-sm shell-text-muted" x-show="notice" x-text="notice"></p>
            </div>
        </section>

        @if ($canViewContent)
            <section class="shell-card p-4 dark:border-slate-700/60 dark:bg-slate-900/40">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="shell-title text-base">Pets Strip</h2>
                    <a href="{{ route('profile.show', ['user' => $profileUser, 'tab' => 'pets']) }}" class="text-xs font-semibold hover:underline" style="color: var(--ui-primary);" aria-label="Open all pets from {{ $profileUser->name }}">
                        See all pets
                    </a>
                </div>

                <div class="-mx-1 flex gap-3 overflow-x-auto px-1 pb-1 scrollbar-subtle">
                    @forelse ($previewPets as $pet)
                        @php
                            $petRouteParam = $pet->slug ?? $pet->getKey();
                        @endphp
                        <a
                            href="{{ route('pets.show', ['slug' => $petRouteParam]) }}"
                            class="min-w-[11.5rem] rounded-xl border border-[var(--ui-border)] bg-[color:var(--ui-surface)] px-3 py-3 transition hover:-translate-y-0.5 hover:border-[var(--ui-border-strong)] dark:border-slate-700/60 dark:bg-slate-900/40"
                            aria-label="View pet profile for {{ $pet->name }}"
                        >
                            <div class="flex items-center gap-2.5">
                                <x-avatar :src="$pet->getFirstMediaUrl('avatar')" :name="$pet->name" size="sm" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">{{ $pet->name }}</p>
                                    <p class="truncate text-xs shell-text-muted">{{ $pet->species }}</p>
                                </div>
                            </div>
                        </a>
                    @empty
                        <p class="text-sm shell-text-muted">No pets published yet.</p>
                    @endforelse
                </div>
            </section>
        @endif

        <section class="shell-card p-3 sm:p-4 dark:border-slate-700/60 dark:bg-slate-900/40">
            <nav class="flex flex-nowrap gap-2 overflow-x-auto pb-1" aria-label="Profile tabs">
                @foreach ($tabs as $key => $label)
                    <a
                        href="{{ route('profile.show', ['user' => $profileUser, 'tab' => $key]) }}"
                        @class([
                            'btn-base px-3 py-2 text-sm',
                            'btn-primary' => $tab === $key,
                            'btn-ghost' => $tab !== $key,
                        ])
                        aria-current="{{ $tab === $key ? 'page' : 'false' }}"
                        aria-label="Open {{ strtolower($label) }} tab"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </section>

        @if (! $canViewContent)
            <section class="shell-card p-6 text-center dark:border-slate-700/60 dark:bg-slate-900/40">
                <p class="text-4xl" aria-hidden="true">🔒</p>
                <h2 class="mt-3 shell-title text-xl">This profile is private</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm shell-text-muted">
                    Follow {{ $profileUser->name }} to view posts, pets, photos, and likes.
                </p>

                @if ($canInteract)
                    <x-follow-button
                        variant="primary"
                        class="mt-4 px-4 py-2"
                        x-bind:disabled="busy || isBlocked"
                        x-bind:aria-pressed="isFollowing.toString()"
                        x-bind:aria-label="isFollowing ? 'Unfollow {{ addslashes($profileUser->name) }}' : 'Follow {{ addslashes($profileUser->name) }}'"
                        @click="toggleFollow"
                    >
                        <span x-text="busy ? 'Saving...' : (isFollowing ? 'Following' : 'Follow to View')"></span>
                    </x-follow-button>
                @endif
            </section>
        @elseif ($tab === 'pets')
            <section class="shell-card p-5 dark:border-slate-700/60 dark:bg-slate-900/40">
                <h2 class="shell-title text-lg">Pets</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @forelse ($pets as $pet)
                        @php
                            $petRouteParam = $pet->slug ?? $pet->getKey();
                        @endphp
                        <a href="{{ route('pets.show', ['slug' => $petRouteParam]) }}" class="rounded-xl border border-[var(--ui-border)] px-4 py-3 transition hover:-translate-y-0.5 hover:border-[var(--ui-border-strong)] dark:border-slate-700/60 dark:bg-slate-900/30" aria-label="Open {{ $pet->name }} pet profile">
                            <div class="flex items-center gap-3">
                                <x-avatar :src="$pet->getFirstMediaUrl('avatar')" :name="$pet->name" size="md" />
                                <div class="min-w-0">
                                    <p class="truncate font-semibold">{{ $pet->name }}</p>
                                    <p class="truncate text-xs shell-text-muted">
                                        {{ $pet->species }}{{ $pet->breed ? ' · '.$pet->breed : '' }}
                                    </p>
                                </div>
                            </div>
                            @if ($pet->bio)
                                <p class="mt-2 line-clamp-2 text-sm shell-text-muted">{{ $pet->bio }}</p>
                            @endif
                        </a>
                    @empty
                        <x-empty-state
                            icon="🐾"
                            title="No pets yet"
                            description="This user has not added pets to their profile."
                        />
                    @endforelse
                </div>
            </section>
        @elseif ($tab === 'photos')
            <section class="shell-card p-5 dark:border-slate-700/60 dark:bg-slate-900/40">
                <h2 class="shell-title text-lg">Photos</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($photos as $photo)
                        <img src="{{ $photo->getUrl() }}" alt="{{ $profileUser->name }} photo" class="h-44 w-full rounded-xl object-cover" />
                    @empty
                        <x-empty-state
                            icon="📷"
                            title="No photos yet"
                            description="When this user shares photos, they will appear here."
                        />
                    @endforelse
                </div>
            </section>
        @elseif ($tab === 'likes')
            <x-empty-state
                icon="❤️"
                title="No likes to show"
                description="Likes tab is ready for Wave 2 data integration."
            />
        @else
            <section class="space-y-4">
                @forelse ($posts as $post)
                    @include('posts.partials.card', ['post' => $post])

                    @if ($isOwner)
                        <div class="-mt-2 flex items-center justify-end gap-2">
                            @if ($post->is_pinned)
                                <form method="POST" action="{{ route('posts.unpin', $post) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-base btn-ghost px-3 py-2 text-xs">Unpin</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('posts.pin', $post) }}">
                                    @csrf
                                    <button type="submit" class="btn-base btn-secondary px-3 py-2 text-xs">Pin to Profile</button>
                                </form>
                            @endif
                        </div>
                    @endif
                @empty
                    <x-empty-state
                        icon="📝"
                        title="No posts yet"
                        description="No posts published yet."
                    />
                @endforelse

                @if (method_exists($posts, 'links'))
                    <div class="shell-card p-3 sm:p-4">
                        {{ $posts->links() }}
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
