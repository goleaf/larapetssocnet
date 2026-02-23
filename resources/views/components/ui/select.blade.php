@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'options' => [],
    'value' => null,
    'selected' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'hint' => null,
])

@php
    $fieldName = $name ?: $attributes->get('name');
    $fieldId = $id ?: $attributes->get('id') ?: ($fieldName ?: 'select-'.\Illuminate\Support\Str::random(6));

    $selectedValue = $value ?? $selected;

    if ($selectedValue === null) {
        $selectedValue = $attributes->get('value');
    }

    if ($selectedValue === null && $fieldName) {
        $selectedValue = old($fieldName);
    }

    $resolvedError = $error;

    if ($resolvedError === null && $fieldName) {
        $resolvedError = $errors->first($fieldName);
    }

    $hasError = filled($resolvedError);
    $hintId = $fieldId.'-hint';

    $baseClasses = 'w-full appearance-none rounded-md border pl-3.5 pr-10 py-2.5 text-sm text-bark transition-all duration-150 focus:outline-none';

    if ($hasError) {
        $stateClasses = 'border-rose bg-rose-light/20 focus:border-rose focus:shadow-[0_0_0_3px_rgba(201,74,90,0.15)]';
    } elseif ($disabled) {
        $stateClasses = 'border-whisker bg-cream opacity-60 cursor-not-allowed';
    } else {
        $stateClasses = 'border-whisker bg-warm-white focus:border-paw focus:shadow-input';
    }

    $classes = \Illuminate\Support\Arr::toCssClasses([
        $baseClasses,
        $stateClasses,
    ]);

    $normalizedOptions = [];

    foreach ($options as $key => $option) {
        if (is_array($option)) {
            $optionValue = $option['value'] ?? $key;
            $optionLabel = $option['label'] ?? (string) $optionValue;
        } else {
            $optionValue = is_int($key) ? $option : $key;
            $optionLabel = (string) $option;
        }

        $normalizedOptions[] = ['value' => $optionValue, 'label' => $optionLabel];
    }

    $selectedValues = is_array($selectedValue) ? $selectedValue : [$selectedValue];

    $controlAttributes = $attributes->except(['class', 'name', 'id', 'value']);
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'flex flex-col gap-1']) }}>
    @if ($label)
        <x-ui.label :for="$fieldId" :required="$required">{{ $label }}</x-ui.label>
    @endif

    <div class="relative">
        <select
            id="{{ $fieldId }}"
            @if ($fieldName)
                name="{{ $fieldName }}"
            @endif
            @if ($required)
                required
            @endif
            @if ($disabled)
                disabled
            @endif
            @if ($hasError)
                aria-invalid="true"
            @endif
            @if ($hasError || $hint)
                aria-describedby="{{ $hintId }}"
            @endif
            {{ $controlAttributes->merge(['class' => $classes]) }}
        >
            @if ($placeholder)
                <option value="" @if (! in_array('', $selectedValues, true)) disabled @endif @selected(in_array('', $selectedValues, true))>
                    {{ $placeholder }}
                </option>
            @endif

            @foreach ($normalizedOptions as $option)
                <option value="{{ $option['value'] }}" @selected(in_array($option['value'], $selectedValues, false))>
                    {{ $option['label'] }}
                </option>
            @endforeach

            {{ $slot }}
        </select>

        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-fur" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </span>
    </div>

    @if ($hasError || $hint)
        <x-ui.hint :id="$hintId" :error="$resolvedError" :message="$hint" />
    @endif
</div>
