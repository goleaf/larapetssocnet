@props([
    'name' => null,
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'hint' => null,
    'help' => null,
    'id' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'state' => null,
    'error' => null,
    'maxlength' => null,
    'showCounter' => true,
])

@php
    $inputName = $name ?? $attributes->get('name');
    $normalizedName = $inputName ? str_replace(['.', '[', ']'], '-', $inputName) : null;
    $inputId = $id ?: ($attributes->get('id') ?: ($normalizedName ?: 'field-'.substr(md5((string) $label), 0, 8)));
    $errorKey = $inputName ?: $inputId;
    $errorMessages = $error ?? $errors->get($errorKey);
    $hasError = filled($errorMessages);
    $resolvedState = $state ?? ($hasError ? 'error' : 'default');
    $hintText = $hint ?? $help;
    $currentValue = $inputName ? old($inputName, $value) : $value;
    $shouldPopulateValue = ! in_array($type, ['password', 'file'], true);
    $maxLength = $maxlength ?? $attributes->get('maxlength');
    $counterEnabled = (bool) $showCounter && filled($maxLength) && ! in_array($type, ['number', 'range', 'date', 'datetime-local', 'time', 'file'], true);
    $hintId = $inputId.'-hint';
    $counterId = $inputId.'-counter';
    $errorId = $inputId.'-error';
    $describedBy = trim(implode(' ', array_filter([
        filled($hintText) ? $hintId : null,
        $counterEnabled ? $counterId : null,
        $hasError ? $errorId : null,
    ])));
@endphp

<div {{ $attributes->only('class') }} x-data="{ length: {{ mb_strlen((string) $currentValue) }} }">
    @if ($label)
        <x-ui.label :for="$inputId" :required="$required">{{ $label }}</x-ui.label>
    @endif

    <input
        id="{{ $inputId }}"
        @if ($inputName)
            name="{{ $inputName }}"
        @endif
        type="{{ $type }}"
        @if ($shouldPopulateValue)
            value="{{ $currentValue }}"
        @endif
        placeholder="{{ $placeholder }}"
        @required($required)
        @disabled($disabled)
        @readonly($readonly)
        @if ($describedBy !== '')
            aria-describedby="{{ $describedBy }}"
        @endif
        aria-invalid="{{ $hasError ? 'true' : 'false' }}"
        @if ($counterEnabled)
            x-on:input="length = $event.target.value.length"
        @endif
        {{ $attributes
            ->except('class', 'id', 'name', 'type', 'value', 'placeholder', 'required', 'disabled', 'readonly')
            ->class([
                'form-input',
                'cursor-not-allowed opacity-60' => $disabled,
                'border-emerald-500 focus:border-emerald-500' => $resolvedState === 'success',
                'border-rose-500 focus:border-rose-500' => $resolvedState === 'error',
            ]) }}
    >

    @if (filled($hintText))
        <x-ui.hint :id="$hintId" class="mt-1">{{ $hintText }}</x-ui.hint>
    @endif

    @if ($counterEnabled)
        <x-ui.hint :id="$counterId" class="mt-1 text-right">
            <span x-text="`${length}/{{ $maxLength }}`"></span>
        </x-ui.hint>
    @endif

    <x-input-error :messages="$errorMessages" :id="$errorId" class="mt-1" />
</div>
