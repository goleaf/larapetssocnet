@props([
    'name',
    'label' => null,
    'rows' => 4,
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'help' => null,
    'id' => null,
])

@php
    $inputId = $id ?: $name;
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-semibold" style="color: var(--ui-text);">
            {{ $label }}
            @if ($required)
                <span style="color: var(--ui-danger);">*</span>
            @endif
        </label>
    @endif

    <textarea
        id="{{ $inputId }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        @required($required)
        {{ $attributes->except('class')->merge(['class' => 'form-textarea']) }}
    >{{ $value }}</textarea>

    @if ($help)
        <p class="mt-1 text-xs shell-text-muted">{{ $help }}</p>
    @endif

    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
