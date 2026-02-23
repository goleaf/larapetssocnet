<x-app-layout>
    @php
        $scopeOptions = [
            ['value' => 'upcoming', 'label' => 'Upcoming'],
            ['value' => 'all', 'label' => 'All'],
            ['value' => 'past', 'label' => 'Past'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
        ];

        if (auth()->check()) {
            $scopeOptions[] = ['value' => 'mine', 'label' => 'Created by me'];
        }

        $groupOptionsForSelect = collect($groupOptions)
            ->map(static fn($groupOption): array => [
                'value' => (string) $groupOption->id,
                'label' => $groupOption->name,
            ])
            ->prepend([
                'value' => '0',
                'label' => 'All groups',
            ])
            ->values()
            ->all();
    @endphp

    <x-slot name="header">
        <x-ui.page-header title="Events" subtitle="Find and join upcoming pet community events.">
            <x-slot name="action">
                @auth
                    <x-ui.button :href="route('events.create')" variant="primary" size="sm">Create Event</x-ui.button>
                @endauth
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <x-flash-message type="success" :message="session('status')" />
        @endif

        <x-ui.card>
            <form method="GET" action="{{ route('events.index') }}" class="grid gap-3 md:grid-cols-12">
                <x-ui.input class="md:col-span-5" name="q" label="Search" :value="$search"
                    placeholder="Search events" />

                <x-ui.select class="md:col-span-3" name="scope" label="Scope" :options="$scopeOptions"
                    :selected="$scope" />

                <x-ui.select class="md:col-span-4" name="group_id" label="Group" :options="$groupOptionsForSelect"
                    :selected="(string) $groupId" />

                <div class="md:col-span-8"></div>

                <div class="flex items-end md:col-span-2">
                    <x-ui.button type="submit" variant="primary" size="sm" class="w-full">Apply Filters</x-ui.button>
                </div>

                <div class="flex items-end md:col-span-2">
                    <x-ui.button :href="route('events.index')" variant="ghost" size="sm"
                        class="w-full">Reset</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        @if ($events->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="📅" title="No Events Found"
                    description="Try changing your filters or create a new event." />
            </x-ui.card>
        @else
            <p class="text-sm text-fur">{{ number_format($events->total()) }} events found</p>

            <div class="mt-4 flex flex-col gap-4 max-w-5xl mx-auto">
                @foreach ($events as $event)
                    @php
                        $startAt = data_get($event, $startColumn) ?? $event->start_at ?? $event->starts_at;
                        $location = data_get($event, 'event_location')
                            ?? data_get($event, 'location_text')
                            ?? data_get($event, 'location');
                        $status = \Illuminate\Support\Str::headline((string) ($event->status ?? 'scheduled'));
                        $groupRouteKey = filled((string) ($event->group_slug ?? '')) ? $event->group_slug : ($event->group_id ?? null);
                    @endphp

                    <x-ui.card>
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-base font-semibold font-display text-bark">
                                <a href="{{ route('events.show', $event->id) }}" class="hover:underline">{{ $event->title }}</a>
                            </h3>
                            <x-ui.badge variant="outline" size="sm">{{ $status }}</x-ui.badge>
                        </div>

                        <div class="mt-3 space-y-1 text-xs text-fur">
                            <p>{{ $startAt ? \Carbon\Carbon::parse($startAt)->format('M j, Y g:i A') : 'TBA' }}</p>

                            @if ($location)
                                <p>{{ $location }}</p>
                            @endif

                            @if (!empty($event->group_name) && $groupRouteKey)
                                <p>
                                    Group:
                                    <a class="underline"
                                        href="{{ route('groups.show', $groupRouteKey) }}">{{ $event->group_name }}</a>
                                </p>
                            @endif

                            @if (!empty($event->creator_name))
                                <p>Host: {{ $event->creator_name }}</p>
                            @endif

                            <p>{{ number_format((int) ($event->attendees_count ?? 0)) }} going</p>
                        </div>

                        <div class="mt-4">
                            <x-ui.button :href="route('events.show', $event->id)" variant="primary" size="sm"
                                class="w-full">View Event</x-ui.button>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            <x-ui.card>
                <x-ui.pagination :paginator="$events" />
            </x-ui.card>
        @endif
    </div>
</x-app-layout>