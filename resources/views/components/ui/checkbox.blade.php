@props([
    'name',
    'label',
    'checked' => false,
    'value' => 1,
    'description' => null,
    'id' => null,
])

@php
    $inputId = $id ?: $name;
@endphp

<div>
    <label for="{{ $inputId }}" class="flex items-start gap-3 rounded-xl border p-3" style="border-color: var(--ui-border); background: color-mix(in srgb, var(--ui-surface) 92%, white 8%);">
        <input
            id="{{ $inputId }}"
            name="{{ $name }}"
            value="{{ $value }}"
            type="checkbox"
            @checked($checked)
            {{ $attributes->merge(['class' => 'mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500']) }}
        >

        <span>
            <span class="block text-sm font-semibold" style="color: var(--ui-text);">{{ $label }}</span>
            @if ($description)
                <span class="mt-0.5 block text-xs shell-text-muted">{{ $description }}</span>
            @endif
        </span>
    </label>

    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
