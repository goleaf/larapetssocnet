@props([
    'user' => null,
    'target' => null,
    'followStatus' => 'none',
    'size' => 'md',
    'showRemove' => false,
])

@php
    $pad = match ($size) {
        'sm' => 'px-3 py-1 text-xs',
        'lg' => 'px-6 py-2.5 text-base',
        default => 'px-4 py-1.5 text-sm',
    };
@endphp

@if ($target !== null && $user === null)
    @php
        $petRouteKey = $target->slug ?? $target->getKey();
        $initiallyFollowing = $followStatus === 'following' || (bool) data_get($target, 'viewer_is_following', false);
    @endphp

    @auth
        <div
            x-data="{
                followed: @js($initiallyFollowing),
                count: {{ (int) ($target->followers_count ?? 0) }},
                loading: false,
                async toggle() {
                    if (this.loading) {
                        return
                    }

                    this.loading = true

                    try {
                        const response = await fetch(this.followed ? @js(route('pets.unfollow', ['slug' => $petRouteKey])) : @js(route('pets.follow', ['slug' => $petRouteKey])), {
                            method: this.followed ? 'DELETE' : 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                        })

                        if (!response.ok) {
                            throw new Error('request_failed')
                        }

                        const data = await response.json()
                        this.followed = !!data.followed
                        this.count = data.followers_count ?? this.count
                    } catch {
                        // noop
                    } finally {
                        this.loading = false
                    }
                },
                get label() {
                    return this.followed ? @js(__('pets.actions.unfollow')) : @js(__('pets.actions.follow'))
                },
            }"
            class="inline-flex items-center gap-2"
        >
            <button
                type="button"
                @click="toggle()"
                :disabled="loading"
                :aria-busy="loading"
                class="{{ $pad }} rounded-lg bg-emerald-600 text-white transition hover:bg-emerald-700 disabled:opacity-60"
            >
                <span x-text="label"></span>
            </button>

            <span class="text-xs text-gray-500" x-text="count"></span>
        </div>
    @else
        <a
            href="{{ route('login') }}"
            class="{{ $pad }} rounded-lg bg-emerald-600 text-white transition hover:bg-emerald-700"
        >
            {{ __('pets.actions.sign_in_to_follow') }}
        </a>
    @endauth
@elseif ($user === null)
    <button
        type="button"
        {{ $attributes->merge(['class' => "{$pad} btn-base btn-primary"]) }}
    >
        {{ $slot }}
    </button>
@else
    <div
        x-data="{
            status: '{{ $followStatus }}',
            count: {{ (int) ($user->followers_count ?? 0) }},
            loading: false,
            get label() {
                const map = { following: 'Following', pending: 'Requested', none: 'Follow' }
                return map[this.status] ?? 'Follow'
            },
            get isActive() {
                return this.status === 'following' || this.status === 'pending'
            },
            get btnStyle() {
                if (this.status === 'following') return 'bg-white border border-gray-300 text-gray-700 hover:border-red-400 hover:text-red-500 hover:bg-red-50'
                if (this.status === 'pending') return 'bg-gray-100 border border-gray-300 text-gray-500'
                return 'bg-emerald-500 hover:bg-emerald-600 text-white border border-transparent'
            },
            async perform(url, method = 'POST') {
                if (this.loading) return
                this.loading = true
                const prev = this.status
                const prevCount = this.count

                if (this.status === 'following') {
                    this.status = 'none'
                    this.count = Math.max(0, this.count - 1)
                }

                try {
                    const res = await fetch(url, {
                        method,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    })

                    if (!res.ok) throw new Error('request_failed')

                    const data = await res.json()
                    if (data.success) {
                        this.status = data.follow_status ?? this.status
                        this.count = data.follower_count ?? this.count

                        window.dispatchEvent(new CustomEvent('follow-toggled', {
                            detail: {
                                userId: {{ $user->id }},
                                followStatus: this.status,
                                followerCount: this.count,
                            },
                        }))
                    } else {
                        this.status = prev
                        this.count = prevCount
                    }
                } catch {
                    this.status = prev
                    this.count = prevCount
                }

                this.loading = false
            },
            toggle() {
                const url = this.isActive
                    ? '/users/{{ $user->username }}/unfollow'
                    : '/users/{{ $user->username }}/follow'
                this.perform(url)
            },
        }"
        class="inline-flex flex-col items-center gap-1"
    >
        <button
            @click="toggle()"
            :disabled="loading || status === 'pending'"
            :aria-busy="loading"
            type="button"
            :class="btnStyle"
            class="{{ $pad }} min-w-[110px] rounded-xl font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
        >
            <span x-show="loading" x-cloak>...</span>
            <span x-show="!loading" x-text="label"></span>
        </button>

        <button
            x-show="status === 'pending'"
            @click="perform('/users/{{ $user->username }}/unfollow')"
            type="button"
            class="text-xs text-gray-400 underline transition-colors hover:text-red-500 focus:outline-none"
        >
            Cancel request
        </button>

        @if ($showRemove)
            <button
                @click="perform('/users/{{ $user->username }}/follower', 'DELETE').then(() => $el.closest('[data-user-card]')?.remove())"
                type="button"
                class="text-xs text-gray-400 underline transition-colors hover:text-red-500 focus:outline-none"
            >
                Remove
            </button>
        @endif
    </div>
@endif
