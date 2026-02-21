@props([
    'name' => 'Group',
    'description' => null,
    'members' => null,
    'privacy' => 'Public',
    'ctaLabel' => null,
    'ctaHref' => '#',
])

<article {{ $attributes->merge(['class' => 'shell-card hover-lift p-4']) }}>
    <h3 class="shell-title text-base">{{ $name }}</h3>

    @if ($description)
        <p class="mt-2 text-sm shell-text-muted">{{ $description }}</p>
    @endif

    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs shell-text-muted">
        @if ($members)
            <span>{{ $members }} members</span>
        @endif
        <span class="chip">{{ $privacy }}</span>
    </div>

    @if ($ctaLabel)
        <a href="{{ $ctaHref }}" class="btn-base btn-primary mt-4 w-full">{{ $ctaLabel }}</a>
    @endif
</article>
