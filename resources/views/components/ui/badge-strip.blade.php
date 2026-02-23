@props([
    'badges' => collect(),
    'max' => 8,
    'badgesUrl' => null,
])

@if ($badges->isNotEmpty())
    <div class="flex flex-wrap items-center gap-2">
        @foreach ($badges->take($max) as $badge)
            <x-ui.tooltip :text="$badge->name">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-paw-light text-sm" title="{{ $badge->name }}">
                    {{ $badge->icon ?? '🏅' }}
                </span>
            </x-ui.tooltip>
        @endforeach

        @if ($badges->count() > $max && $badgesUrl)
            <a href="{{ $badgesUrl }}" class="inline-flex h-8 items-center rounded-full bg-cream px-3 text-xs font-semibold text-paw hover:bg-paw-light transition-colors">
                +{{ $badges->count() - $max }} more
            </a>
        @endif
    </div>
@endif
