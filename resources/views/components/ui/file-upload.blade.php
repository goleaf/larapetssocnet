@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'accept' => null,
    'multiple' => false,
    'required' => false,
    'disabled' => false,
    'maxSize' => null,
    'preview' => false,
    'hint' => null,
    'help' => null,
    'error' => null,
])

@php
    $fieldName = $name ?: $attributes->get('name');
    $inputName = $fieldName;

    if ($multiple && $inputName && ! str_ends_with($inputName, '[]')) {
        $inputName .= '[]';
    }

    $sanitizedName = preg_replace('/\[.*$/', '', (string) $fieldName);
    $fieldId = $id ?: $attributes->get('id') ?: ($sanitizedName !== '' ? $sanitizedName : 'file-upload-'.\Illuminate\Support\Str::random(6));

    $resolvedHelp = $help ?? $hint;
    $resolvedError = $error;

    if ($resolvedError === null && $sanitizedName !== '') {
        $resolvedError = $errors->first($sanitizedName);
    }

    $maxBytes = null;

    if ($maxSize !== null && trim((string) $maxSize) !== '') {
        $rawSize = trim((string) $maxSize);

        if (preg_match('/^(\d+)(?:\s*)([a-zA-Z]+)?$/', $rawSize, $matches) === 1) {
            $number = (int) $matches[1];
            $unit = strtolower($matches[2] ?? 'b');

            $power = match ($unit) {
                'kb', 'k' => 1,
                'mb', 'm' => 2,
                'gb', 'g' => 3,
                default => 0,
            };

            $maxBytes = $number * (1024 ** $power);
        }
    }

    $errorId = $fieldId.'-error';
    $helpId = $fieldId.'-help';

    $describedBy = trim(collect([
        $resolvedError ? $errorId : null,
        $resolvedHelp ? $helpId : null,
    ])->filter()->implode(' '));

    $controlAttributes = $attributes->except(['class', 'name', 'id']);
@endphp

<div
    {{ $attributes->only('class')->merge(['class' => 'flex flex-col gap-1']) }}
    x-data="{
        dragging: false,
        files: [],
        previews: [],
        errorMessage: @js((string) ($resolvedError ?? '')),
        maxBytes: @js($maxBytes),
        maxLabel: @js($maxSize),
        previewEnabled: @js((bool) $preview),
        multiple: @js((bool) $multiple),
        disabled: @js((bool) $disabled),
        clearPreviews() {
            this.previews.forEach((previewItem) => {
                if (previewItem.url) {
                    URL.revokeObjectURL(previewItem.url);
                }
            });

            this.previews = [];
        },
        syncInput() {
            const data = new DataTransfer();
            this.files.forEach((file) => data.items.add(file));
            this.$refs.input.files = data.files;
        },
        updatePreviews() {
            this.clearPreviews();

            if (!this.previewEnabled) {
                return;
            }

            this.files.forEach((file) => {
                if (file.type.startsWith('image/')) {
                    this.previews.push({ name: file.name, image: true, url: URL.createObjectURL(file) });
                    return;
                }

                this.previews.push({ name: file.name, image: false, url: null });
            });
        },
        setFiles(fileList) {
            if (this.disabled) {
                return;
            }

            let incomingFiles = Array.from(fileList || []);

            if (!this.multiple) {
                incomingFiles = incomingFiles.slice(0, 1);
            }

            if (this.maxBytes !== null) {
                const oversizedFile = incomingFiles.find((file) => file.size > this.maxBytes);

                if (oversizedFile) {
                    this.errorMessage = this.maxLabel
                        ? `File exceeds maximum size of ${this.maxLabel}`
                        : 'Selected file is too large.';
                    this.files = [];
                    this.syncInput();
                    this.clearPreviews();
                    return;
                }
            }

            this.errorMessage = '';
            this.files = incomingFiles;
            this.syncInput();
            this.updatePreviews();
        },
        openPicker() {
            if (!this.disabled) {
                this.$refs.input.click();
            }
        },
        handleDrop(event) {
            this.dragging = false;

            if (event.dataTransfer?.files?.length) {
                this.setFiles(event.dataTransfer.files);
            }
        }
    }"
    x-on:dragover.prevent="if (!disabled) dragging = true"
    x-on:dragleave.prevent="dragging = false"
    x-on:drop.prevent="handleDrop($event)"
    x-on:keydown.enter.prevent="openPicker()"
    x-on:keydown.space.prevent="openPicker()"
>
    @if ($label)
        <x-ui.label :for="$fieldId" :required="$required">{{ $label }}</x-ui.label>
    @endif

    <div
        class="relative flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-6 text-center transition-all duration-150"
        :class="{
            'border-paw bg-paw-light/60': dragging && !disabled,
            'border-rose bg-rose-light/20': errorMessage,
            'border-whisker bg-warm-white hover:bg-cream': !dragging && !errorMessage && !disabled,
            'border-whisker bg-cream cursor-not-allowed opacity-70': disabled,
        }"
        role="button"
        tabindex="0"
        aria-controls="{{ $fieldId }}"
        @click="openPicker()"
    >
        <input
            x-ref="input"
            type="file"
            id="{{ $fieldId }}"
            class="sr-only"
            @if ($inputName)
                name="{{ $inputName }}"
            @endif
            @if ($accept)
                accept="{{ $accept }}"
            @endif
            @if ($multiple)
                multiple
            @endif
            @if ($required)
                required
            @endif
            @if ($disabled)
                disabled
            @endif
            @if ($describedBy !== '')
                aria-describedby="{{ $describedBy }}"
            @endif
            @if ($resolvedError)
                aria-invalid="true"
            @endif
            x-on:change="setFiles($event.target.files)"
            {{ $controlAttributes }}
        />

        <span class="inline-flex h-11 w-11 items-center justify-center rounded-pill bg-paw-light text-xl" aria-hidden="true">🐾</span>
        <p class="text-sm font-medium text-bark">Drop files here or click to browse</p>
        <p class="text-xs text-fur">
            @if ($accept)
                {{ str_replace(',', ', ', $accept) }}
            @endif
            @if ($accept && $maxSize)
                •
            @endif
            @if ($maxSize)
                Max {{ $maxSize }}
            @endif
        </p>
    </div>

    <div x-show="previews.length && previewEnabled" x-cloak class="mt-2 flex flex-wrap gap-3">
        <template x-for="(previewItem, index) in previews" :key="index">
            <div class="h-24 w-24 overflow-hidden rounded-md border border-whisker bg-cream">
                <template x-if="previewItem.image">
                    <img :src="previewItem.url" :alt="previewItem.name" class="h-full w-full object-cover" />
                </template>
                <template x-if="!previewItem.image">
                    <div class="flex h-full items-center justify-center px-2 text-center text-xs text-fur" x-text="previewItem.name"></div>
                </template>
            </div>
        </template>
    </div>

    <div x-show="files.length && !previewEnabled" x-cloak class="mt-2 text-xs text-fur">
        <p class="font-medium text-bark" x-text="files.length === 1 ? '1 file selected' : `${files.length} files selected`"></p>
        <ul class="mt-1 list-inside list-disc">
            <template x-for="(file, index) in files" :key="index">
                <li class="truncate" x-text="file.name"></li>
            </template>
        </ul>
    </div>

    <p id="{{ $errorId }}" x-show="errorMessage" x-cloak class="mt-1 flex items-center gap-1 text-xs text-rose">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
        </svg>
        <span x-text="errorMessage"></span>
    </p>

    @if ($resolvedHelp)
        <x-ui.hint id="{{ $helpId }}" :message="$resolvedHelp" x-show="!errorMessage" />
    @endif
</div>
