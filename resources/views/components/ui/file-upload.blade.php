@props([
    'name',
    'label' => null,
    'accept' => null,
    'multiple' => false,
    'help' => null,
    'id' => null,
])

@php
    $inputId = $id ?: $name;
    $errorKey = str_ends_with($name, '[]') ? substr($name, 0, -2) : $name;
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-semibold" style="color: var(--ui-text);">
            {{ $label }}
        </label>
    @endif

    <div class="rounded-xl border border-dashed p-3" style="border-color: var(--ui-border-strong); background: color-mix(in srgb, var(--ui-surface-muted) 76%, var(--ui-surface) 24%);">
        <input
            id="{{ $inputId }}"
            name="{{ $name }}"
            type="file"
            @if ($accept) accept="{{ $accept }}" @endif
            @if ($multiple) multiple @endif
            {{ $attributes->except('class')->merge(['class' => 'block w-full cursor-pointer text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-emerald-700']) }}
        >

        @if ($help)
            <p class="mt-2 text-xs shell-text-muted">{{ $help }}</p>
        @endif
    </div>

    <x-input-error :messages="$errors->get($errorKey)" class="mt-2" />
    <x-input-error :messages="$errors->get($errorKey.'.*')" class="mt-2" />
</div>
