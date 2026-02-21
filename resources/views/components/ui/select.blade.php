@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'required' => false,
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

    <select id="{{ $inputId }}" name="{{ $name }}" @required($required) {{ $attributes->except('class')->merge(['class' => 'form-select']) }}>
        @if (! is_null($placeholder))
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
