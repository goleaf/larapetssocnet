@section('title', 'Messages')

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="shell-kicker">Direct Messages</p>
                <h1 class="shell-title text-2xl">Inbox</h1>
                <p class="mt-1 text-sm shell-text-muted">Conversations grouped by contact, newest first.</p>
            </div>

            <a href="{{ route('marketplace.index') }}" class="btn-base btn-ghost px-3 py-2 text-sm">Marketplace</a>
        </div>
    </x-slot>

    <div class="space-y-4">
        <form method="GET" action="{{ route('messages.index') }}" class="shell-panel p-4">
            <label for="q" class="shell-kicker">Search Conversations</label>
            <div class="mt-2 flex items-center gap-2">
                <input id="q" name="q" type="text" value="{{ $search }}" placeholder="Name or username" class="form-input">
                <button type="submit" class="btn-base btn-primary px-3 py-2 text-sm">Search</button>
            </div>
        </form>

        @if ($threads->isEmpty())
            <x-empty-state
                icon="💬"
                title="No conversations yet"
                description="Start a conversation from a user profile, listing page, or event card."
            />
        @else
            <section class="shell-card overflow-hidden p-0">
                @foreach ($threads as $thread)
                    @php
                        $peer = $thread['peer'];
                        $latest = $thread['latest_message'];
                        $isUnread = $thread['unread_count'] > 0;
                    @endphp

                    <a
                        href="{{ route('messages.conversation', ['peer' => $peer]) }}"
                        class="group flex items-center justify-between gap-4 border-b px-4 py-4 transition-colors last:border-b-0"
                        style="border-color: var(--ui-border); background: {{ $isUnread ? 'color-mix(in srgb, var(--ui-primary) 6%, var(--ui-surface) 94%)' : 'transparent' }};"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <x-avatar :src="$peer->avatar_url" :name="$peer->name" size="md" :status="$isUnread ? 'online' : null" />

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-sm font-semibold" style="color: var(--ui-text);">{{ $peer->name }}</p>
                                    @if ($peer->username)
                                        <span class="truncate text-xs shell-text-muted">{{ '@'.$peer->username }}</span>
                                    @endif
                                </div>

                                <p class="truncate text-sm {{ $isUnread ? 'font-semibold' : 'shell-text-muted' }}" style="color: {{ $isUnread ? 'var(--ui-text)' : 'var(--ui-text-muted)' }};">
                                    {{ $latest->body }}
                                </p>
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="text-xs shell-text-muted">{{ $latest->created_at?->diffForHumans() }}</p>

                            @if ($thread['unread_count'] > 0)
                                <span class="mt-1 inline-flex items-center rounded-full bg-emerald-500 px-2 py-0.5 text-xs font-semibold text-white">
                                    {{ $thread['unread_count'] }}
                                </span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </section>
        @endif
    </div>
</x-app-layout>
