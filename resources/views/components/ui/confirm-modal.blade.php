@props([
    'id' => null,
    'name' => null,
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'description' => null,
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'variant' => 'danger',
    'trigger' => false,
    'formId' => null,
])

@php
    $modalId = $name ?: ($id ?: 'confirm-modal-' . \Illuminate\Support\Str::random(8));
    $messageText = $description ?? $message;
    $triggerSlot = $trigger ?? $triggerSlot ?? null;
@endphp

<div
    {{ $attributes->except('class')->merge(['class' => 'contents']) }}
    x-data="{
        open: false,
        globalMode: false,
        title: @js($title),
        message: @js($messageText),
        confirmLabel: @js($confirmLabel),
        cancelLabel: @js($cancelLabel),
        variant: @js($variant),
        formId: @js($formId),

        syncFromStore() {
            if (!this.$store.confirm) {
                return;
            }

            this.title = this.$store.confirm.title || @js($title);
            this.message = this.$store.confirm.message || @js($messageText);
            this.confirmLabel = this.$store.confirm.confirmLabel || @js($confirmLabel);
            this.cancelLabel = this.$store.confirm.cancelLabel || @js($cancelLabel);
            this.variant = this.$store.confirm.variant || @js($variant);
        },

        iconClasses() {
            if (this.variant === 'danger') {
                return 'bg-rose-light text-rose';
            }

            if (this.variant === 'warning') {
                return 'bg-amber-light text-amber';
            }

            if (this.variant === 'success') {
                return 'bg-leaf-light text-leaf';
            }

            return 'bg-paw-light text-paw-dark';
        },

        show() {
            this.open = true;
            document.body.classList.add('overflow-hidden');
        },

        hide() {
            this.open = false;
            document.body.classList.remove('overflow-hidden');
            this.$dispatch('close');
        },

        confirm() {
            if (this.formId) {
                const form = document.getElementById(this.formId);
                if (form) {
                    form.submit();
                }
            }

            if (this.globalMode && this.$store.confirm) {
                this.$store.confirm.confirm();
            }

            this.$dispatch('confirm');
            this.$dispatch('confirm-modal-accepted', { id: @js($modalId) });
            this.hide();
        },

        cancel() {
            if (this.globalMode && this.$store.confirm) {
                this.$store.confirm.cancel();
            }

            this.$dispatch('cancel');
            this.$dispatch('confirm-modal-cancelled', { id: @js($modalId) });
            this.hide();
        },
    }"
    @open-modal.window="if ($event.detail === @js($modalId)) { globalMode = false; show(); }"
    @close-modal.window="if ($event.detail === @js($modalId)) { hide(); }"
    @keydown.escape.window="if (open) { cancel(); }"
    x-init="if ($store.confirm) { $watch('$store.confirm.open', value => { if (value) { globalMode = true; syncFromStore(); show(); } else if (globalMode && open) { hide(); } }); }"
>
    @if($trigger && $triggerSlot)
        <div class="inline-block" @click="show()">
            {{ $triggerSlot }}
        </div>
    @endif

    <div
        x-show="open"
        x-cloak
        style="display: none;"
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-modal-title-{{ $modalId }}"
    >
        <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
            <div
                x-show="open"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-bark/40 backdrop-blur-sm"
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
                class="inline-block w-full transform overflow-hidden rounded-xl bg-warm-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:max-w-md sm:align-middle"
                @click.stop
            >
                <div class="px-6 pb-6 pt-8 text-center sm:text-left">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-pill sm:mx-0 sm:h-10 sm:w-10" :class="iconClasses()">
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>

                        <div class="mt-4 sm:ml-4 sm:mt-0">
                            <h3 class="text-lg font-semibold font-display text-bark" id="confirm-modal-title-{{ $modalId }}" x-text="title"></h3>
                            <div class="mt-2">
                                <p class="text-sm text-fur" x-text="message"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse items-center justify-end gap-3 border-t border-whisker/40 bg-cream/50 px-6 py-4 sm:flex-row">
                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center rounded-md border border-whisker bg-transparent px-5 py-2.5 text-sm font-medium text-bark shadow-sm transition-all duration-150 hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw sm:w-auto"
                        @click="cancel()"
                    >
                        <span x-text="cancelLabel"></span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center rounded-md px-5 py-2.5 text-sm font-medium text-white shadow-button transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw sm:w-auto"
                        :class="{
                            'bg-rose hover:bg-red-700': variant === 'danger',
                            'bg-amber hover:bg-amber-600': variant === 'warning',
                            'bg-leaf hover:bg-leaf/90': variant === 'success',
                            'bg-paw hover:bg-paw-dark': !['danger', 'warning', 'success'].includes(variant),
                        }"
                        @click="confirm()"
                    >
                        <span x-text="confirmLabel"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
