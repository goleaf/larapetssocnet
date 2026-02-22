@props([
    'title' => null,
    'subtitle' => null,
    'collapsible' => false,
    'open' => true,
])

<div 
    {{ $attributes->merge(['class' => 'bg-warm-white rounded-lg shadow-card overflow-hidden']) }}
    @if($collapsible) x-data="{ open: {{ $open ? 'true' : 'false' }} }" @endif
>
    @if($title)
        <div 
            class="px-4 py-3 border-b border-whisker/40 flex justify-between items-center transition-colors {{ $collapsible ? 'cursor-pointer hover:bg-cream' : '' }}"
            @if($collapsible) @click="open = !open" @endif
        >
            <div>
                <h4 class="font-semibold font-display text-bark">{{ $title }}</h4>
                @if($subtitle)
                    <p class="text-xs text-fur mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            
            @if($collapsible)
                <div class="text-whisker transition-transform duration-200" :class="open ? 'rotate-180' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                        <path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 01-1.06-.02L10 8.832 6.29 12.77a.75.75 0 11-1.08-1.04l4.25-4.5a.75.75 0 011.08 0l4.25 4.5a.75.75 0 01-.02 1.06z" clip-rule="evenodd" />
                    </svg>
                </div>
            @endif
        </div>
    @endif
    
    <div class="p-4" @if($collapsible) x-show="open" x-collapse @endif>
        {{ $slot }}
    </div>
</div>
