@props([
    'title',
    'description' => null,
    'icon' => null,
])

<section {{ $attributes->merge(['class' => 'shell-card space-y-5 p-5 sm:p-6']) }}>
    <header class="space-y-1">
        <div class="flex items-center gap-2">
            @if ($icon)
                <span class="text-xl">{{ $icon }}</span>
            @endif

            <h2 class="shell-title text-lg">{{ $title }}</h2>
        </div>

        @if ($description)
            <p class="text-sm shell-text-muted">{{ $description }}</p>
        @endif
    </header>

    {{ $slot }}
</section>
