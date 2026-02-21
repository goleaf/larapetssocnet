@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'columns' => 2,
])

@php
    $gridClass = match ((int) $columns) {
        1 => 'grid-cols-1',
        3 => 'grid-cols-1 md:grid-cols-3',
        4 => 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4',
        default => 'grid-cols-1 md:grid-cols-2',
    };
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <p class="mb-1.5 text-sm font-semibold" style="color: var(--ui-text);">{{ $label }}</p>
    @endif

    <div class="grid {{ $gridClass }} gap-2">
        @foreach ($options as $optionValue => $optionLabel)
            <label class="flex items-center gap-2 rounded-xl border px-3 py-2" style="border-color: var(--ui-border); background: color-mix(in srgb, var(--ui-surface) 92%, white 8%);">
                <input
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $optionValue }}"
                    @checked((string) $value === (string) $optionValue)
                    {{ $attributes->except('class')->merge(['class' => 'border-gray-300 text-emerald-600 focus:ring-emerald-500']) }}
                >
                <span class="text-sm font-medium" style="color: var(--ui-text);">{{ $optionLabel }}</span>
            </label>
        @endforeach
    </div>

    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
