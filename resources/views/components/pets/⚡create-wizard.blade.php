<?php

use Livewire\Component;

new class extends Component
{
    //
};

?>

<div
    id="pet-create-wizard"
    x-data="{ open: false, source: 'default' }"
    x-on:pet-create-wizard-open.window="open = true; source = $event.detail?.source || 'default'"
    x-on:keydown.escape.window="open = false"
    x-cloak
>
    <div
        x-show="open"
        class="fixed inset-0 z-50 flex items-end justify-center bg-bark/45 px-4 py-6 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pet-create-wizard-title"
    >
        <div class="w-full max-w-lg rounded-lg border border-whisker/40 bg-warm-white p-5 shadow-soft">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="pet-create-wizard-title" class="text-lg font-semibold text-bark">Add a pet</h2>
                    <p class="mt-1 text-sm text-fur">Open the full pet setup flow to add profile details, story, and photos.</p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-whisker/40 px-2 py-1 text-sm font-semibold text-fur transition hover:bg-cream hover:text-bark"
                    x-on:click="open = false"
                    aria-label="Close pet create dialog"
                >
                    &times;
                </button>
            </div>

            <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                <x-ui.button type="button" variant="ghost" x-on:click="open = false">Cancel</x-ui.button>
                <x-ui.button href="{{ route('pets.create') }}" variant="primary">Continue</x-ui.button>
            </div>
        </div>
    </div>
</div>
