<a
    {{ $attributes->merge(['class' => 'block w-full rounded-lg px-3 py-2 text-left text-sm font-semibold transition hover:bg-emerald-500/10']) }}
    style="color: var(--ui-text);"
>
    {{ $slot }}
</a>
