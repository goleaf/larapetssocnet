@section('title', 'Messages')

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header
            title="Inbox"
            subtitle="Conversations grouped by contact, newest first."
            :breadcrumbs="[
                ['label' => 'Messages'],
            ]"
            icon="📬"
        >
            <x-slot:action>
                <x-ui.button variant="outline" size="sm" :href="route('marketplace.index')" icon="🛍️">
                    Marketplace
                </x-ui.button>
            </x-slot:action>
        </x-ui.page-header>
    </x-slot>

    @php
        $threadCount = $threads->count();
        $unreadThreads = $threads->filter(fn (array $thread): bool => ((int) ($thread['unread_count'] ?? 0)) > 0)->count();
        $unreadMessages = (int) $threads->sum(fn (array $thread): int => (int) ($thread['unread_count'] ?? 0));
        $featuredPeers = $threads->take(5)->pluck('peer')->all();
        $firstUnread = $threads->first(fn (array $thread): bool => ((int) ($thread['unread_count'] ?? 0)) > 0);
    @endphp

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_19rem]">
        <div class="space-y-4">
            <x-ui.card>
                <form method="GET" action="{{ route('messages.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <x-ui.input
                            name="q"
                            label="Search Conversations"
                            type="search"
                            placeholder="Search by name or username"
                            :value="$search"
                            prefix="🔎"
                        />
                    </div>

                    <x-ui.button type="submit" variant="primary" icon="Search">
                        Search
                    </x-ui.button>
                </form>
            </x-ui.card>

            @if ($threads->isEmpty())
                <x-ui.empty-state
                    icon="💬"
                    title="No conversations yet"
                    description="Start a conversation from a user profile, listing page, or event card."
                >
                    <x-slot:action>
                        <x-ui.button :href="route('marketplace.index')" icon="🛍️" variant="secondary">
                            Explore Marketplace
                        </x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <x-ui.card padding="none" class="overflow-hidden">
                    <div class="border-b border-whisker/30 bg-gradient-to-r from-paw-light/40 via-warm-white to-leaf-light/35 px-4 py-3 sm:px-5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-fur">Active Conversations</p>
                                <h2 class="text-lg font-semibold font-display text-bark">Your Chats</h2>
                            </div>

                            <x-ui.badge :variant="$unreadMessages > 0 ? 'success' : 'default'" size="md" dot>
                                {{ $unreadMessages }} unread
                            </x-ui.badge>
                        </div>
                    </div>

                    <div class="divide-y divide-whisker/25">
                        @foreach ($threads as $thread)
                            @php
                                $peer = $thread['peer'];
                                $latest = $thread['latest_message'];
                                $isUnread = ((int) ($thread['unread_count'] ?? 0)) > 0;
                                $preview = trim((string) ($latest->body ?? 'Message unavailable'));
                            @endphp

                            <a
                                href="{{ route('messages.conversation', ['peer' => $peer]) }}"
                                class="group block px-4 py-4 transition-all duration-150 hover:bg-cream/70 sm:px-5 {{ $isUnread ? 'bg-paw-light/30' : 'bg-warm-white' }}"
                            >
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <x-ui.avatar :src="$peer->avatar_url" :name="$peer->name" size="md" :online="$isUnread" />

                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <p class="truncate text-sm font-semibold {{ $isUnread ? 'text-bark' : 'text-fur group-hover:text-bark' }}">
                                                    {{ $peer->name }}
                                                </p>

                                                @if ($peer->username)
                                                    <span class="truncate text-xs text-whisker">&#64;{{ $peer->username }}</span>
                                                @endif
                                            </div>

                                            <p class="mt-0.5 truncate text-sm {{ $isUnread ? 'font-medium text-bark' : 'text-fur' }}">
                                                {{ $preview }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <p class="text-xs text-fur">{{ $latest->created_at?->diffForHumans() }}</p>

                                        @if ($isUnread)
                                            <x-ui.badge variant="success" size="sm" class="mt-1">
                                                {{ (int) $thread['unread_count'] }}
                                            </x-ui.badge>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif
        </div>

        <aside class="space-y-4">
            <x-ui.card>
                <x-slot:header>
                    <x-ui.card-header title="Inbox Snapshot" subtitle="Quick conversation metrics" icon="📊" />
                </x-slot:header>

                <x-ui.data-list
                    :items="[
                        ['label' => 'Total threads', 'value' => (string) $threadCount],
                        ['label' => 'Unread threads', 'value' => (string) $unreadThreads],
                        ['label' => 'Unread messages', 'value' => (string) $unreadMessages],
                    ]"
                    divided
                />

                @if (! empty($featuredPeers))
                    <x-ui.divider class="my-4" />

                    <div class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-fur">Recent Contacts</p>
                        <x-ui.avatar-group :users="$featuredPeers" :total="$threadCount" :max="5" size="sm" />
                    </div>
                @endif

                <div class="mt-4 space-y-2">
                    @if ($firstUnread)
                        <x-ui.button
                            full
                            variant="primary"
                            size="sm"
                            :href="route('messages.conversation', ['peer' => $firstUnread['peer']])"
                            icon="⚡"
                        >
                            Jump to Unread
                        </x-ui.button>
                    @endif

                    <x-ui.button full variant="outline" size="sm" :href="route('marketplace.index')" icon="🛍️">
                        Open Marketplace
                    </x-ui.button>
                </div>
            </x-ui.card>
        </aside>
    </div>
</x-app-layout>
