@props([
    'title' => 'Upcoming Event',
    'startsAt' => null,
    'location' => null,
    'host' => null,
    'attendees' => null,
    'ctaLabel' => null,
    'ctaHref' => '#',
])

<article {{ $attributes->merge(['class' => 'shell-card p-4']) }}>
    <h3 class="shell-title text-base">{{ $title }}</h3>

    <div class="mt-3 space-y-1 text-xs shell-text-muted">
        @if ($startsAt)
            <p>🕒 {{ $startsAt }}</p>
        @endif
        @if ($location)
            <p>📍 {{ $location }}</p>
        @endif
        @if ($host)
            <p>👤 {{ $host }}</p>
        @endif
        @if ($attendees)
            <p>🙌 {{ $attendees }} attending</p>
        @endif
    </div>

    @if ($ctaLabel)
        <a href="{{ $ctaHref }}" class="btn-base btn-secondary mt-4 w-full">{{ $ctaLabel }}</a>
    @endif
</article>
