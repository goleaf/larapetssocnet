<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Events</h2>
            @auth
                <a href="{{ route('events.create') }}" class="btn-base btn-primary px-3 py-2 text-sm">Create Event</a>
            @endauth
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <x-flash-message type="success" :message="session('status')" />
            @endif

            <form method="GET" action="{{ route('events.index') }}" class="shell-card grid gap-3 p-4 md:grid-cols-4">
                <div class="md:col-span-2">
                    <x-input-label for="q" :value="'Search'" />
                    <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$search" placeholder="Search events..." />
                </div>

                <div>
                    <x-input-label for="scope" :value="'Scope'" />
                    <select id="scope" name="scope" class="mt-1 block w-full rounded-md border border-[var(--ui-border)] bg-white px-3 py-2 text-sm">
                        <option value="upcoming" @selected($scope === 'upcoming')>Upcoming</option>
                        <option value="all" @selected($scope === 'all')>All</option>
                        <option value="past" @selected($scope === 'past')>Past</option>
                        <option value="cancelled" @selected($scope === 'cancelled')>Cancelled</option>
                        @auth
                            <option value="mine" @selected($scope === 'mine')>Created by me</option>
                        @endauth
                    </select>
                </div>

                <div>
                    <x-input-label for="group_id" :value="'Group'" />
                    <select id="group_id" name="group_id" class="mt-1 block w-full rounded-md border border-[var(--ui-border)] bg-white px-3 py-2 text-sm">
                        <option value="0">All groups</option>
                        @foreach ($groupOptions as $groupOption)
                            <option value="{{ $groupOption->id }}" @selected((int) $groupId === (int) $groupOption->id)>{{ $groupOption->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-4 flex justify-end">
                    <button type="submit" class="btn-base btn-primary">Apply Filters</button>
                </div>
            </form>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($events as $event)
                    @php
                        $startAt = data_get($event, $startColumn) ?? $event->start_at ?? $event->starts_at;
                        $location = data_get($event, 'event_location')
                            ?? data_get($event, 'location_text')
                            ?? data_get($event, 'location');
                        $status = $event->status ?? 'scheduled';
                        $groupRouteKey = filled((string) ($event->group_slug ?? '')) ? $event->group_slug : ($event->group_id ?? null);
                    @endphp

                    <article class="shell-card p-5">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="shell-title text-base">
                                <a href="{{ route('events.show', $event->id) }}">{{ $event->title }}</a>
                            </h3>
                            <span class="chip">{{ \Illuminate\Support\Str::headline($status) }}</span>
                        </div>

                        <div class="mt-3 space-y-1 text-xs shell-text-muted">
                            <p>
                                {{ $startAt ? \Carbon\Carbon::parse($startAt)->format('M j, Y g:i A') : 'TBA' }}
                            </p>
                            @if ($location)
                                <p>{{ $location }}</p>
                            @endif
                            @if (! empty($event->group_name) && $groupRouteKey)
                                <p>
                                    Group:
                                    <a class="underline" href="{{ route('groups.show', $groupRouteKey) }}">{{ $event->group_name }}</a>
                                </p>
                            @endif
                            @if (! empty($event->creator_name))
                                <p>Host: {{ $event->creator_name }}</p>
                            @endif
                            <p>{{ $event->attendees_count ?? 0 }} going</p>
                        </div>

                        <div class="mt-4">
                            <a href="{{ route('events.show', $event->id) }}" class="btn-base btn-primary w-full justify-center text-sm">View Event</a>
                        </div>
                    </article>
                @empty
                    <x-empty-state
                        title="No Events Found"
                        description="Try changing your filters or create a new event."
                    />
                @endforelse
            </div>

            <div>
                {{ $events->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
