@props(['events'])

<section class="rounded-2xl border border-gray-200 bg-white p-4">
    <h3 class="text-sm font-semibold text-gray-900">Upcoming Events</h3>
    <ul class="mt-3 space-y-3">
        @foreach ($events as $event)
            <li>
                <a href="{{ route('events.show', $event) }}" class="block rounded-lg border border-gray-100 p-2 hover:bg-gray-50">
                    <p class="text-sm font-medium text-gray-900">{{ $event->title }}</p>
                    <p class="text-xs text-gray-500">{{ optional($event->start_at)->format('M j, g:i A') }}</p>
                    <p class="text-xs text-gray-500">{{ $event->location_text }}</p>
                </a>
            </li>
        @endforeach
    </ul>
</section>
