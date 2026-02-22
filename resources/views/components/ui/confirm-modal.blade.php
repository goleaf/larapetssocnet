@props([
    'id' => 'confirm-modal-' . \Illuminate\Support\Str::random(8),
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'variant' => 'danger',
    'trigger' => false,
])

<div 
    x-data="{ 
        open: false,
        isGlobalStoreMode: false,
        title: '{{ str_replace('\'', '\\\'', $title) }}',
        message: '{{ str_replace('\'', '\\\'', $message) }}',
        confirmLabel: '{{ str_replace('\'', '\\\'', $confirmLabel) }}',
        cancelLabel: '{{ str_replace('\'', '\\\'', $cancelLabel) }}',
        variant: '{{ str_replace('\'', '\\\'', $variant) }}',
        
        getIconColors() {
            if (this.variant === 'danger') return 'text-rose bg-rose-light';
            if (this.variant === 'warning') return 'text-amber bg-amber-light';
            return 'text-paw-dark bg-paw-light';
        },
        
        getButtonVariant() {
            return this.variant;
        },
        
        confirm() {
            if (this.isGlobalStoreMode) {
                Alpine.store('confirm').confirm();
            } else {
                this.open = false;
                $dispatch('confirm-modal-accepted', { id: '{{ $id }}' });
            }
        },
        
        cancel() {
            if (this.isGlobalStoreMode) {
                Alpine.store('confirm').cancel();
            } else {
                this.open = false;
                $dispatch('confirm-modal-cancelled', { id: '{{ $id }}' });
            }
        }
    }"
    @open-global-confirm.window="
        isGlobalStoreMode = true;
        title = $store.confirm.title || 'Are you sure?';
        message = $store.confirm.message;
        confirmLabel = $store.confirm.confirmLabel || 'Confirm';
        cancelLabel = $store.confirm.cancelLabel || 'Cancel';
        variant = $store.confirm.variant || 'danger';
        open = true;
    "
    @open-modal.window="if ($event.detail === '{{ $id }}') { open = true; isGlobalStoreMode = false; }"
    @close-modal.window="if ($event.detail === '{{ $id }}') { open = false }"
    x-init="
        $watch('$store.confirm.open', value => {
            if (value && !open) {
                $dispatch('open-global-confirm');
            } else if (!value && open && isGlobalStoreMode) {
                open = false;
            }
        })
    "
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
            <div 
                x-show="open" 
                x-transition:enter="ease-out duration-300" 
                x-transition:enter-start="opacity-0" 
                x-transition:enter-end="opacity-100" 
                x-transition:leave="ease-in duration-200" 
                x-transition:leave-start="opacity-100" 
                x-transition:leave-end="opacity-0" 
                class="fixed inset-0 bg-bark/40 backdrop-blur-sm transition-opacity" 
                @click="cancel()"
                aria-hidden="true"
            ></div>

            <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

            <div 
                x-show="open" 
                x-transition:enter="ease-out duration-300 transform" 
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                x-transition:leave="ease-in duration-200 transform" 
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                class="inline-block w-full transform overflow-hidden rounded-xl bg-warm-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle sm:max-w-md"
                @click.stop
            >
                <div class="px-6 pb-6 pt-8 text-center sm:text-left">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-pill sm:mx-0 sm:h-10 sm:w-10" :class="getIconColors()">
                            <template x-if="variant === 'danger'">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </template>
                            <template x-if="variant === 'warning'">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </template>
                            <template x-if="variant === 'primary'">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                                </svg>
                            </template>
                        </div>
                        <div class="mt-4 sm:ml-4 sm:mt-0">
                            <h3 class="text-lg font-semibold font-display text-bark" id="modal-title" x-text="title"></h3>
                            <div class="mt-2">
                                <p class="text-sm text-fur" x-text="message"></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-whisker/40 bg-cream/50 px-6 py-4 flex flex-col-reverse sm:flex-row items-center justify-end gap-3">
                    <button type="button" @click="cancel()" class="w-full sm:w-auto inline-flex items-center justify-center font-medium transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw border border-whisker text-bark bg-transparent hover:bg-cream px-5 py-2.5 text-sm rounded-md shadow-sm">
                        <span x-text="cancelLabel"></span>
                    </button>
                    
                    <button 
                        type="button" 
                        @click="confirm()" 
                        class="w-full sm:w-auto inline-flex items-center justify-center font-medium transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw px-5 py-2.5 text-sm rounded-md shadow-button text-white"
                        :class="{
                            'bg-rose hover:bg-red-700': variant === 'danger',
                            'bg-amber hover:bg-amber-600': variant === 'warning',
                            'bg-paw hover:bg-paw-dark': variant === 'primary',
                        }"
                    >
                        <span x-text="confirmLabel"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
