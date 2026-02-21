@props([
    'name',
    'title' => 'Confirm action',
    'description' => 'This action cannot be undone.',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'formId' => null,
])

<x-modal :name="$name" maxWidth="md" focusable>
    <div class="p-5 sm:p-6">
        <h3 class="shell-title text-lg">{{ $title }}</h3>
        <p class="mt-2 text-sm shell-text-muted">{{ $description }}</p>

        <div class="mt-5 flex flex-wrap justify-end gap-2">
            <x-ui.button variant="ghost" type="button" x-on:click="$dispatch('close')">
                {{ $cancelLabel }}
            </x-ui.button>

            @if ($formId)
                <x-ui.button variant="danger" type="submit" form="{{ $formId }}">
                    {{ $confirmLabel }}
                </x-ui.button>
            @elseif (isset($actions))
                {{ $actions }}
            @endif
        </div>
    </div>
</x-modal>
