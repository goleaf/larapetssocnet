@props([
    'name',
    'label' => null,
    'options' => [], /* array of ['value' => '', 'label' => ''] or associative [value => label] */
    'selected' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'hint' => null,
])

@php
    $error = $error ?? $errors->first($name);
    
    $baseClasses = 'w-full border rounded-md pl-3.5 pr-10 py-2.5 text-bark text-sm font-body transition-all duration-150 focus:outline-none appearance-none';
    
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

<div class="flex flex-col gap-1 {{ $attributes->get('class') }}">
    @if($label)
        <x-ui.label :for="$name" :required="$required">{{ $label }}</x-ui.label>
    @endif
    
    <div class="relative w-full">
        <select 
            name="{{ $name }}" 
            id="{{ $name }}"
            {{ $disabled ? 'disabled' : '' }}
            {{ $required ? 'required' : '' }}
            {!! $attributes->except(['class'])->merge(['class' => $classes]) !!} 
        >
            @if($placeholder)
                <option value="" disabled @if(!$selected) selected @endif>{{ $placeholder }}</option>
            @endif
            
            @foreach($options as $key => $option)
                @php
                    $optValue = is_array($option) ? ($option['value'] ?? $key) : $key;
                    $optLabel = is_array($option) ? ($option['label'] ?? $option) : $option;
                    $isSelected = $selected == $optValue || (is_array($selected) && in_array($optValue, $selected));
                @endphp
                <option value="{{ $optValue }}" @if($isSelected) selected @endif>
                    {{ $optLabel }}
                </option>
            @endforeach
            
            {{ $slot }}
        </select>
        
        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-fur">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </div>
    </div>
    
    <x-ui.hint :error="$error">
        @if($hint) {{ $hint }} @endif
    </x-ui.hint>
</div>
