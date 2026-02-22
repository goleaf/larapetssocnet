@props([
    'name',
    'label' => null,
    'options' => [], /* array of ['value', 'label', 'description'] */
    'selected' => null,
    'required' => false,
    'error' => null,
])

@php
    $error = $error ?? $errors->first($name);
@endphp

<div class="flex flex-col gap-2 {{ $attributes->get('class') }}" x-data="{ selected: '{{ $selected }}' }">
    @if($label)
        <x-ui.label :for="$name" :required="$required">{{ $label }}</x-ui.label>
    @endif
    
    <div class="space-y-2">
        @foreach($options as $option)
            @php
                $optValue = is_array($option) ? ($option['value'] ?? '') : $option;
                $optLabel = is_array($option) ? ($option['label'] ?? $optValue) : $optValue;
                $optDesc = is_array($option) ? ($option['description'] ?? null) : null;
                $isSelected = $selected == $optValue;
            @endphp
            
            <label 
                class="flex items-start gap-4 cursor-pointer w-full transition-all duration-150 relative"
                :class="selected == '{{ $optValue }}' ? 'border-2 border-paw bg-paw-light rounded-lg p-3' : 'border border-whisker bg-warm-white rounded-lg p-3 hover:bg-cream'"
            >
                <input 
                    type="radio" 
                    name="{{ $name }}" 
                    value="{{ $optValue }}"
                    x-model="selected"
                    class="sr-only peer"
                    {{ $required ? 'required' : '' }}
                    {{ $isSelected ? 'checked' : '' }}
                />
                
                <div 
                    class="w-5 h-5 rounded-pill flex items-center justify-center border mt-0.5 shrink-0 transition-all duration-150 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-paw"
                    :class="selected == '{{ $optValue }}' ? 'border-paw' : 'border-whisker bg-warm-white'"
                >
                    <div class="w-2.5 h-2.5 rounded-pill bg-paw transition-transform duration-150" :class="selected == '{{ $optValue }}' ? 'scale-100' : 'scale-0'"></div>
                </div>
                
                <div class="flex flex-col flex-1">
                    <span class="text-sm font-medium text-bark">{{ $optLabel }}</span>
                    @if($optDesc)
                        <span class="text-xs text-fur mt-0.5">{{ $optDesc }}</span>
                    @endif
                </div>
            </label>
        @endforeach
    </div>
    
    <x-ui.hint :error="$error" />
</div>
