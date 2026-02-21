@php
    $avatarUrl = $profileUser->getFirstMediaUrl('avatar');
    $coverUrl = $profileUser->getFirstMediaUrl('cover');
    $tabs = ['posts' => 'Posts', 'pets' => 'Pets', 'photos' => 'Photos', 'likes' => 'Likes'];
    $canInteract = auth()->check() && auth()->id() !== $profileUser->id;
@endphp

@section('title', $profileUser->name.' Profile')

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="shell-title text-xl">{{ $profileUser->name }}</h1>
            <p class="mt-1 text-sm shell-text-muted">@{{ $profileUser->username }}</p>
        </div>
    </x-slot>

    <div
        class="space-y-5"
        x-data="{
            isFollowing: @js($isFollowing),
            isBlocked: @js($isBlocked),
            busy: false,
            notice: '',
            async send(url, method) {
                this.busy = true;
                try {
                    const response = await fetch(url, {
                        method,
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        }
                    });
                    const payload = await response.json();
                    this.notice = payload.message;
                    if (payload.success) {
                        if (Object.hasOwn(payload.data, 'is_following')) {
                            this.isFollowing = payload.data.is_following;
                        }
                        if (Object.hasOwn(payload.data, 'is_blocked')) {
                            this.isBlocked = payload.data.is_blocked;
                        }
                    }
                } finally {
                    this.busy = false;
                }
            },
            async toggleFollow() {
                if (this.isFollowing) {
                    await this.send('{{ route('users.unfollow', ['user' => $profileUser]) }}', 'DELETE');
                    return;
                }

                await this.send('{{ route('users.follow', ['user' => $profileUser]) }}', 'POST');
            },
            async toggleBlock() {
                if (this.isBlocked) {
                    await this.send('{{ route('users.unblock', ['user' => $profileUser]) }}', 'DELETE');
                    return;
                }

                await this.send('{{ route('users.block', ['user' => $profileUser]) }}', 'POST');
            }
        }"
    >
        <section class="shell-card overflow-hidden">
            @if ($coverUrl)
                <img src="{{ $coverUrl }}" alt="{{ $profileUser->name }} cover image" class="h-40 w-full object-cover sm:h-52" />
            @else
                <div class="h-40 w-full sm:h-52" style="background: color-mix(in srgb, var(--ui-primary) 10%, var(--ui-surface) 90%);"></div>
            @endif

            <div class="px-5 pb-5 pt-0 sm:px-6">
                <div class="-mt-10 flex flex-wrap items-end justify-between gap-4">
                    <div class="flex items-end gap-3">
                        <x-avatar :src="$avatarUrl" :name="$profileUser->name" size="2xl" class="shadow-sm" />
                        <div>
                            <p class="shell-title text-xl">{{ $profileUser->name }}</p>
                            <p class="text-sm shell-text-muted">@{{ $profileUser->username }}</p>
                        </div>
                    </div>

                    @if ($canInteract)
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn-base btn-primary px-3 py-2 text-sm" :disabled="busy || isBlocked" @click="toggleFollow">
                                <span x-text="isFollowing ? 'Following' : 'Follow'"></span>
                            </button>
                            <button type="button" class="btn-base btn-ghost px-3 py-2 text-sm" :disabled="busy" @click="toggleBlock">
                                <span x-text="isBlocked ? 'Unblock' : 'Block'"></span>
                            </button>
                        </div>
                    @endif
                </div>

                <div class="mt-4 grid grid-cols-3 gap-3 sm:max-w-md">
                    <a href="{{ route('profile.followers', ['user' => $profileUser]) }}" class="rounded-xl border border-[var(--ui-border)] px-3 py-2 text-center">
                        <p class="text-xl font-bold">{{ $profileUser->followers_count }}</p>
                        <p class="text-xs shell-text-muted">Followers</p>
                    </a>
                    <a href="{{ route('profile.following', ['user' => $profileUser]) }}" class="rounded-xl border border-[var(--ui-border)] px-3 py-2 text-center">
                        <p class="text-xl font-bold">{{ $profileUser->following_count }}</p>
                        <p class="text-xs shell-text-muted">Following</p>
                    </a>
                    <div class="rounded-xl border border-[var(--ui-border)] px-3 py-2 text-center">
                        <p class="text-xl font-bold">{{ $profileUser->pets_count }}</p>
                        <p class="text-xs shell-text-muted">Pets</p>
                    </div>
                </div>

                @if ($profileUser->bio)
                    <p class="mt-4 text-sm">{{ $profileUser->bio }}</p>
                @endif

                <p class="mt-3 text-sm shell-text-muted" x-show="notice" x-text="notice"></p>
            </div>
        </section>

        <section class="shell-card p-4">
            <div class="flex flex-wrap gap-2">
                @foreach ($tabs as $key => $label)
                    <a
                        href="{{ route('profile.show', ['user' => $profileUser, 'tab' => $key]) }}"
                        class="btn-base {{ $tab === $key ? 'btn-primary' : 'btn-ghost' }} px-3 py-2 text-sm"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </section>

        @if (! $canViewContent)
            <x-empty-state
                icon="🔒"
                title="This profile is private"
                description="Follow this user to view their posts, pets, photos, and likes."
            />
        @elseif ($tab === 'pets')
            <section class="shell-card p-5">
                <h2 class="shell-title text-lg">Pets</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($pets as $pet)
                        <article class="rounded-xl border border-[var(--ui-border)] px-4 py-3">
                            <p class="font-semibold">{{ $pet->name }} <span class="shell-text-muted">· {{ $pet->species }}</span></p>
                            @if ($pet->bio)
                                <p class="mt-1 text-sm shell-text-muted">{{ $pet->bio }}</p>
                            @endif
                        </article>
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
            <section class="shell-card p-5">
                <h2 class="shell-title text-lg">Photos</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($photos as $photo)
                        <img src="{{ $photo->getUrl() }}" alt="{{ $profileUser->name }} photo" class="h-40 w-full rounded-xl object-cover" />
                    @empty
                        <x-empty-state
                            icon="📷"
                            title="No photos yet"
                            description="When this user shares photos, they'll appear here."
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
            <x-empty-state
                icon="📝"
                title="No posts yet"
                description="Posts tab is ready for Wave 2 feed integration."
            />
        @endif
    </div>
</x-app-layout>
