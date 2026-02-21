@props([
    'tabs' => [],
])

@php
    $normalizedTabs = collect($tabs)->map(function ($tab, $index) {
        if (is_string($tab)) {
            return [
                'label' => $tab,
                'href' => null,
                'active' => false,
                'count' => null,
            ];
        }

        if (is_array($tab)) {
            return [
                'label' => (string) ($tab['label'] ?? 'Tab '.($index + 1)),
                'href' => $tab['href'] ?? null,
                'active' => (bool) ($tab['active'] ?? false),
                'count' => $tab['count'] ?? null,
            ];
        }

        return [
            'label' => 'Tab '.($index + 1),
            'href' => null,
            'active' => false,
            'count' => null,
        ];
    })->values();
@endphp

<nav {{ $attributes->merge(['class' => 'shell-card flex flex-wrap items-center gap-2 p-3']) }} aria-label="Listing filters">
    @foreach ($normalizedTabs as $tab)
        @php
            $buttonClass = $tab['active'] ? 'btn-primary' : 'btn-ghost';
        @endphp

        @if ($tab['href'])
            <a href="{{ $tab['href'] }}" class="btn-base {{ $buttonClass }} px-3 py-2 text-sm">
                <span>{{ $tab['label'] }}</span>
                @if (! is_null($tab['count']))
                    <span class="chip">{{ number_format((int) $tab['count']) }}</span>
                @endif
            </a>
        @else
            <button type="button" class="btn-base {{ $buttonClass }} px-3 py-2 text-sm">
                <span>{{ $tab['label'] }}</span>
                @if (! is_null($tab['count']))
                    <span class="chip">{{ number_format((int) $tab['count']) }}</span>
                @endif
            </button>
        @endif
    @endforeach
</nav>
