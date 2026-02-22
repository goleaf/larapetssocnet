@props([
    'name',
    'label' => null,
    'rows' => 4,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'hint' => null,
    'maxlength' => null,
])

@php
    $error = $error ?? $errors->first($name);
    
    $baseClasses = 'w-full border rounded-md px-3.5 py-2.5 text-bark text-sm font-body placeholder:text-whisker transition-all duration-150 focus:outline-none resize-y';
    
    if ($error) {
        $stateClasses = 'border-rose focus:border-rose focus:shadow-[0_0_0_3px_rgba(201,74,90,0.15)] bg-rose-light/20';
    } elseif ($disabled) {
        $stateClasses = 'bg-cream border-whisker opacity-60 cursor-not-allowed';
    } else {
        $stateClasses = 'bg-warm-white border-whisker focus:border-paw focus:shadow-input';
    }
    
    $classes = \Illuminate\Support\Arr::toCssClasses([
        $baseClasses,
        $stateClasses,
    ]);
@endphp

<div class="flex flex-col gap-1 {{ $attributes->get('class') }}"
    @if($maxlength)
        x-data="{ count: {{ strlen((string) $attributes->get('value', '')) }}, max: {{ $maxlength }} }"
    @endif
>
    @if($label)
        <x-ui.label :for="$name" :required="$required">{{ $label }}</x-ui.label>
    @endif
    
    <textarea 
        name="{{ $name }}" 
        id="{{ $name }}"
        rows="{{ $rows }}"
        {{ $disabled ? 'disabled' : '' }}
        {{ $required ? 'required' : '' }}
        @if($maxlength)
            maxlength="{{ $maxlength }}"
            x-on:input="count = $event.target.value.length"
        @endif
        {!! $attributes->except(['class'])->merge(['class' => $classes]) !!} 
    >{{ $slot->isNotEmpty() ? $slot : $attributes->get('value') }}</textarea>
    
    <div class="flex justify-between items-start gap-4">
        <div class="flex-1">
            <x-ui.hint :error="$error">
                @if($hint) {{ $hint }} @endif
            </x-ui.hint>
        </div>
        
        @if($maxlength)
            <div class="text-xs mt-1 shrink-0" :class="count >= max ? 'text-rose font-medium' : 'text-whisker'">
                <span x-text="count"></span> / <span x-text="max"></span>
            </div>
        @endif
    </div>
</div>
