@props([
    'name',
    'label' => null,
    'type' => 'text',
    'required' => false,
    'disabled' => false,
    'error' => null,
    'hint' => null,
    'prefix' => null,
    'suffix' => null,
])

@php
    $error = $error ?? $errors->first($name);
    
    $baseClasses = 'w-full border rounded-md px-3.5 py-2.5 text-bark text-sm font-body placeholder:text-whisker transition-all duration-150 focus:outline-none';
    
    if ($error) {
        $stateClasses = 'border-rose focus:border-rose focus:shadow-[0_0_0_3px_rgba(201,74,90,0.15)] bg-rose-light/20';
    } elseif ($disabled) {
        $stateClasses = 'bg-cream border-whisker opacity-60 cursor-not-allowed';
    } else {
        $stateClasses = 'bg-warm-white border-whisker focus:border-paw focus:shadow-input';
    }
    
    $paddingClasses = '';
    if ($prefix) $paddingClasses .= ' pl-9';
    if ($suffix) $paddingClasses .= ' pr-9';
    
    $classes = \Illuminate\Support\Arr::toCssClasses([
        $baseClasses,
        $stateClasses,
        $paddingClasses,
    ]);
@endphp

<div class="flex flex-col gap-1 {{ $attributes->get('class') }}">
    @if($label)
        <x-ui.label :for="$name" :required="$required">{{ $label }}</x-ui.label>
    @endif
    
    <div class="relative flex items-center w-full">
        @if($prefix)
            <div class="absolute left-3 text-fur pointer-events-none flex items-center justify-center">
                {{ $prefix }}
            </div>
        @endif
        
        <input 
            type="{{ $type }}" 
            name="{{ $name }}" 
            id="{{ $name }}"
            {{ $disabled ? 'disabled' : '' }}
            {{ $required ? 'required' : '' }}
            {!! $attributes->except(['class']) !!} 
            class="{{ $classes }}" 
        />
        
        @if($suffix)
            <div class="absolute right-3 text-fur pointer-events-none flex items-center justify-center">
                {{ $suffix }}
            </div>
        @endif
    </div>
    
    <x-ui.hint :error="$error">
        @if($hint) {{ $hint }} @endif
    </x-ui.hint>
</div>
