<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 leading-tight">Messages</h2>
                <p class="mt-1 text-sm text-gray-600">Inbox grouped by conversation partner.</p>
            </div>

            <a href="{{ route('marketplace.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to Marketplace</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('messages.index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <label for="q" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Search Conversations</label>
                <div class="mt-2 flex items-center gap-2">
                    <input id="q" name="q" type="text" value="{{ $search }}" placeholder="Name or username"
                        class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Search</button>
                </div>
            </form>

            @if ($threads->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-600">
                    No conversations yet.
                </div>
            @else
                <div class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    @foreach ($threads as $thread)
                        @php
                            $peer = $thread['peer'];
                            $latest = $thread['latest_message'];
                            $isUnread = $thread['unread_count'] > 0;
                        @endphp

                        <a href="{{ route('messages.conversation', ['peer' => $peer]) }}" class="flex items-center justify-between gap-4 px-4 py-4 hover:bg-gray-50">
                            <div class="flex min-w-0 items-center gap-3">
                                <x-avatar :src="$peer->avatar_url" :name="$peer->name" size="md" />

                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="truncate font-semibold text-gray-900">{{ $peer->name }}</p>
                                        @if ($peer->username)
                                            <span class="truncate text-xs text-gray-500">{{ '@'.$peer->username }}</span>
                                        @endif
                                    </div>

                                    <p class="truncate text-sm {{ $isUnread ? 'font-semibold text-gray-900' : 'text-gray-600' }}">
                                        {{ $latest->body }}
                                    </p>
                                </div>
                            </div>

                            <div class="shrink-0 text-right">
                                <p class="text-xs text-gray-500">{{ $latest->created_at?->diffForHumans() }}</p>

                                @if ($thread['unread_count'] > 0)
                                    <span class="mt-1 inline-flex items-center rounded-full bg-blue-600 px-2 py-0.5 text-xs font-semibold text-white">
                                        {{ $thread['unread_count'] }}
                                    </span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
