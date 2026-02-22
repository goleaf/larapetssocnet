@props([
    'name',
    'label' => null,
    'value' => '1',
    'checked' => false,
    'disabled' => false,
    'hint' => null,
    'error' => null,
])

@php
    $error = $error ?? $errors->first($name);
@endphp

<div class="flex items-start gap-3 {{ $attributes->get('class') }}" x-data="{ checked: {{ $checked ? 'true' : 'false' }} }">
    <div class="flex items-center h-5 mt-0.5">
        <input 
            type="checkbox" 
            name="{{ $name }}" 
            id="{{ $name }}" 
            value="{{ $value }}"
            class="peer sr-only"
            {{ $disabled ? 'disabled' : '' }}
            {{ $checked ? 'checked' : '' }}
            x-model="checked"
            {!! $attributes->except(['class']) !!}
        />
        
        <div 
            class="w-5 h-5 rounded flex items-center justify-center border transition-all duration-150 cursor-pointer peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-paw"
            :class="{
                'bg-paw border-paw opacity-50 cursor-not-allowed': checked && {{ $disabled ? 'true' : 'false' }},
                'bg-paw border-paw': checked && !{{ $disabled ? 'true' : 'false' }},
                'bg-warm-white border-whisker': !checked && !{{ $disabled ? 'true' : 'false' }},
                'bg-cream border-whisker opacity-50 cursor-not-allowed': !checked && {{ $disabled ? 'true' : 'false' }},
                'border-rose': {{ $error ? 'true' : 'false' }} 
            }"
            @click="!{{ $disabled ? 'true' : 'false' }} && (checked = !checked)"
        >
            <svg x-show="checked" style="display: none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5 text-white">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
            </svg>
        </div>
    </div>
    
    <div class="flex flex-col">
        @if($label)
            <label for="{{ $name }}" class="text-sm font-medium font-body transition-colors cursor-pointer {{ $disabled ? 'text-whisker cursor-not-allowed' : 'text-bark' }}" @click="!{{ $disabled ? 'true' : 'false' }} && document.getElementById('{{ $name }}').click()">
                {{ $label }}
            </label>
        @endif
        
        <x-ui.hint :error="$error">
            @if($hint) {{ $hint }} @endif
        </x-ui.hint>
    </div>
</div>
