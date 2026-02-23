@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'rows' => 4,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'hint' => null,
    'maxlength' => null,
    'value' => null,
])

@php
    $fieldName = $name ?: $attributes->get('name');
    $fieldId = $id ?: $attributes->get('id') ?: ($fieldName ?: 'textarea-'.\Illuminate\Support\Str::random(6));

    $fieldValue = $value;

    if ($fieldValue === null) {
        $fieldValue = $attributes->get('value');
    }

    if ($fieldValue === null && $fieldName) {
        $fieldValue = old($fieldName);
    }

    if ($fieldValue === null && $slot->isNotEmpty()) {
        $fieldValue = trim($slot->toHtml());
    }

    $resolvedError = $error;

    if ($resolvedError === null && $fieldName) {
        $resolvedError = $errors->first($fieldName);
    }

    $hasError = filled($resolvedError);
    $hintId = $fieldId.'-hint';
    $counterId = $fieldId.'-counter';
    $initialCount = mb_strlen((string) ($fieldValue ?? ''));
    $describedBy = trim(collect([
        ($hasError || $hint) ? $hintId : null,
        $maxlength ? $counterId : null,
    ])->filter()->implode(' '));

    $baseClasses = 'w-full resize-y rounded-md border px-3.5 py-2.5 text-sm text-bark placeholder:text-whisker transition-all duration-150 focus:outline-none';

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

    $controlAttributes = $attributes->except(['class', 'name', 'id', 'value']);
@endphp

<div
    {{ $attributes->only('class')->merge(['class' => 'flex flex-col gap-1']) }}
    @if ($maxlength)
        x-data="{ count: {{ $initialCount }}, max: {{ (int) $maxlength }} }"
    @endif
>
    @if ($label)
        <x-ui.label :for="$fieldId" :required="$required">{{ $label }}</x-ui.label>
    @endif

    <textarea
        id="{{ $fieldId }}"
        rows="{{ $rows }}"
        @if ($fieldName)
            name="{{ $fieldName }}"
        @endif
        @if ($required)
            required
        @endif
        @if ($disabled)
            disabled
        @endif
        @if ($maxlength)
            maxlength="{{ (int) $maxlength }}"
            x-on:input="count = $event.target.value.length"
        @endif
        @if ($hasError)
            aria-invalid="true"
        @endif
        @if ($describedBy !== '')
            aria-describedby="{{ $describedBy }}"
        @endif
        {{ $controlAttributes->merge(['class' => $classes]) }}
    >{{ $fieldValue }}</textarea>

    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            @if ($hasError || $hint)
                <x-ui.hint :id="$hintId" :error="$resolvedError" :message="$hint" />
            @endif
        </div>

        @if ($maxlength)
            <p
                id="{{ $counterId }}"
                class="shrink-0 pt-1 text-xs text-whisker"
                :class="count >= max ? 'font-medium text-rose' : ''"
            >
                <span x-text="count"></span>/<span x-text="max"></span>
            </p>
        @endif
    </div>
</div>
