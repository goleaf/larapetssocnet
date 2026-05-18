@section('title', __('messages.index.title'))

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header
            :title="__('messages.index.heading')"
            :subtitle="__('messages.index.subtitle')"
            :breadcrumbs="[['label' => __('messages.index.heading')]]"
            icon="💬"
        />
    </x-slot>

    <div class="w-full min-w-0 space-y-4" data-ui="messages-page">
        <x-ui.card>
            <form method="GET" action="{{ route('messages.index') }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                <div class="min-w-0">
                    <x-ui.input
                        name="q"
                        type="search"
                        :placeholder="__('messages.index.search_placeholder')"
                        :value="$search"
                        prefix="🔎"
                    />
                </div>

                <x-ui.button type="submit" variant="primary" size="sm" class="w-full sm:w-auto sm:min-w-32">
                    {{ __('messages.actions.search') }}
                </x-ui.button>
            </form>
        </x-ui.card>

        @if ($threads->isEmpty())
            <x-ui.empty-state
                icon="💬"
                :title="__('messages.index.empty_title')"
                :description="__('messages.index.empty_description')"
            />
        @else
            <x-ui.card padding="none" class="overflow-hidden">
                <div class="divide-y divide-whisker/25">
                    @foreach ($threads as $thread)
                        <a
                            href="{{ route('messages.conversation', ['peer' => $thread['peer']]) }}"
                            class="block px-4 py-3 transition-colors duration-150 hover:bg-cream/60 {{ ((int) ($thread['unread_count'] ?? 0)) > 0 ? 'bg-paw-light/25' : 'bg-warm-white' }}"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <x-user-avatar :user="$thread['peer']" size="sm" />

                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-bark">{{ $thread['peer']->name }}</p>
                                        <p class="truncate text-xs text-fur">
                                            {{ $thread['peer']->username ? '@'.$thread['peer']->username : __('messages.index.default_peer_label') }}
                                        </p>
                                        <p class="mt-0.5 truncate text-sm {{ ((int) ($thread['unread_count'] ?? 0)) > 0 ? 'font-medium text-bark' : 'text-fur' }}">
                                            {{ trim((string) (($thread['latest_message']->body ?? __('messages.index.unavailable_preview')))) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="shrink-0 text-right">
                                    <p class="text-xs text-fur">{{ optional($thread['latest_message']->created_at)->diffForHumans() }}</p>

                                    @if (((int) ($thread['unread_count'] ?? 0)) > 0)
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
</x-app-layout>
