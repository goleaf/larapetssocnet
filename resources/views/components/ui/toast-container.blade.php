<div x-data class="fixed bottom-4 right-4 z-[60] flex flex-col-reverse gap-2 pointer-events-none" aria-live="polite">
    <template x-for="toast in $store.toast.items" :key="toast.id">
        <div x-show="true" x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-4"
            class="pointer-events-auto flex items-center gap-3 rounded-lg bg-warm-white border border-whisker/30 shadow-card-hover px-4 py-3 min-w-[280px] max-w-sm">
            <span class="shrink-0 h-full w-1 rounded-pill self-stretch" :class="{
                    'bg-leaf':  toast.type === 'success',
                    'bg-rose':  toast.type === 'error',
                    'bg-amber': toast.type === 'warning',
                    'bg-sky':   toast.type === 'info',
                    'bg-paw':   !['success','error','warning','info'].includes(toast.type),
                }"></span>
            <span class="shrink-0 text-base" x-text="({
                    success: '✅',
                    error:   '❌',
                    warning: '⚠️',
                    info:    'ℹ️',
                })[toast.type] || '🐾'"></span>
            <p class="text-sm text-bark flex-1" x-text="toast.message"></p>
            <button @click="$store.toast.remove(toast.id)" class="shrink-0 text-fur hover:text-bark transition-colors"
                aria-label="Dismiss">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path
                        d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
            </button>
        </div>
    </template>
</div>