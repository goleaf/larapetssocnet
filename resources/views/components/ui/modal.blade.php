@props([
    'id' => \Illuminate\Support\Str::random(8),
    'title' => null,
    'size' => 'md',
    'trigger' => true,
])

@php
    $maxWidths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-2xl',
    ];

    $maxWidth = $maxWidths[$size] ?? $maxWidths['md'];
@endphp

<div 
    x-data="{ open: false }"
    @open-modal.window="if ($event.detail === '{{ $id }}') { open = true }"
    @close-modal.window="if ($event.detail === '{{ $id }}') { open = false }"
    @keydown.escape.window="open = false"
>
    @if($trigger && isset($triggerSlot))
        <div @click="open = true" class="inline-block">
            {{ $triggerSlot }}
        </div>
    @endif
    
    <div 
        x-show="open" 
        style="display: none;" 
        class="fixed inset-0 z-50 overflow-y-auto" 
        aria-labelledby="modal-title" 
        role="dialog" 
        aria-modal="true"
    >
        <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Backdrop -->
            <div 
                x-show="open" 
                x-transition:enter="ease-out duration-300" 
                x-transition:enter-start="opacity-0" 
                x-transition:enter-end="opacity-100" 
                x-transition:leave="ease-in duration-200" 
                x-transition:leave-start="opacity-100" 
                x-transition:leave-end="opacity-0" 
                class="fixed inset-0 bg-bark/40 backdrop-blur-sm transition-opacity" 
                @click="open = false"
                aria-hidden="true"
            ></div>

            <!-- Alignment trick -->
            <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

            <!-- Modal Panel -->
            <div 
                x-show="open" 
                x-transition:enter="ease-out duration-300 transform" 
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                x-transition:leave="ease-in duration-200 transform" 
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                class="inline-block w-full transform overflow-hidden rounded-xl bg-warm-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle {{ $maxWidth }}"
            >
                @if(isset($header))
                    {{ $header }}
                @elseif($title)
                    <div class="flex items-center justify-between border-b border-whisker/40 px-6 py-4">
                        <h3 class="text-lg font-semibold font-display text-bark" id="modal-title">{{ $title }}</h3>
                        <button type="button" @click="open = false" class="text-whisker hover:text-bark focus:outline-none focus:ring-2 focus:ring-paw rounded-sm transition-colors">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @endif
                
                <div class="px-6 py-5">
                    @if(isset($body))
                        {{ $body }}
                    @else
                        {{ $slot }}
                    @endif
                </div>
                
                @if(isset($footer))
                    <div class="border-t border-whisker/40 bg-cream/50 px-6 py-4 flex items-center justify-end gap-3">
                        {{ $footer }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
