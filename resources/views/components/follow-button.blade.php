@props([
    'user' => null,
    'target' => null,
    'followStatus' => 'none',
    'size' => 'md',
    'showRemove' => false,
])

@if ($target !== null && $user === null)
    @auth
        <div
            x-data="{
                followed: @js($followStatus === 'following' || (bool) data_get($target, 'viewer_is_following', false)),
                count: {{ (int) ($target->followers_count ?? 0) }},
                loading: false,
                async toggle() {
                    if (this.loading) {
                        return
                    }

                    this.loading = true

                    try {
                        const response = await fetch(this.followed ? @js(route('pets.unfollow', ['pet' => $target->slug ?? $target->getKey()])) : @js(route('pets.follow', ['pet' => $target->slug ?? $target->getKey()])), {
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
            <x-ui.button
                type="button"
                :size="$size"
                variant="outline"
                @click="toggle()"
                x-bind:disabled="loading"
                x-bind:aria-busy="loading"
                x-bind:class="followed ? 'border-whisker/40 text-bark bg-warm-white hover:bg-cream' : 'border-transparent bg-paw text-white hover:bg-paw-dark'"
                class="min-w-[110px] justify-center"
            >
                <span x-text="label"></span>
            </x-ui.button>

            <span class="text-xs text-fur" x-text="count"></span>
        </div>
    @else
        <x-ui.button
            :href="route('login')"
            :size="$size"
            variant="primary"
            class="min-w-[110px] justify-center"
        >
            {{ __('pets.actions.sign_in_to_follow') }}
        </x-ui.button>
    @endauth
@elseif ($user === null)
    <x-ui.button
        type="button"
        :size="$size"
        variant="primary"
        {{ $attributes->merge(['class' => 'min-w-[110px] justify-center']) }}
    >
        {{ $slot }}
    </x-ui.button>
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
        <x-ui.button
            type="button"
            :size="$size"
            :disabled="false"
            @click="toggle()"
            x-bind:aria-busy="loading"
            x-bind:disabled="loading || status === 'pending'"
            x-bind:class="status === 'following'
                ? 'border-rose/40 text-rose hover:bg-rose-light/40'
                : (status === 'pending'
                    ? 'border-whisker/40 text-fur bg-cream'
                    : 'border-transparent bg-paw text-white hover:bg-paw-dark')"
            variant="outline"
            class="min-w-[110px] justify-center"
        >
            <span x-show="loading" x-cloak>...</span>
            <span x-show="!loading" x-text="label"></span>
        </x-ui.button>

        <button
            x-show="status === 'pending'"
            @click="perform('/users/{{ $user->username }}/unfollow')"
            type="button"
            class="text-xs text-fur underline transition-colors hover:text-red-500 focus:outline-none"
        >
            Cancel request
        </button>

        @if ($showRemove)
            <button
                @click="perform('/users/{{ $user->username }}/follower', 'DELETE').then(() => $el.closest('[data-user-card]')?.remove())"
                type="button"
                class="text-xs text-fur underline transition-colors hover:text-red-500 focus:outline-none"
            >
                Remove
            </button>
        @endif
    </div>
@endif
