@props([
    'name',
    'label' => null,
    'accept' => null,
    'multiple' => false,
    'maxSize' => null,
    'preview' => false,
    'hint' => null,
    'error' => null,
])

@php
    $error = $error ?? $errors->first($name);
    
    // Parse max size string into bytes (e.g. "20MB" -> 20971520)
    $maxBytes = 0;
    if ($maxSize) {
        $val = trim($maxSize);
        preg_match('/([0-9]+)[\s]*([a-zA-Z]+)/', $val, $matches);
        if (count($matches) === 3) {
            $num = (int) $matches[1];
            $unit = strtolower($matches[2]);
            $power = match($unit) {
                'kb', 'k' => 1,
                'mb', 'm' => 2,
                'gb', 'g' => 3,
                default => 0
            };
            $maxBytes = $num * pow(1024, $power);
        } else {
            $maxBytes = (int) $val; // Assume bytes
        }
    }
@endphp

<div class="flex flex-col gap-1 {{ $attributes->get('class') }}"
    x-data="{ 
        isDropping: false, 
        files: [],
        previewUrls: [],
        errorMsg: '{{ str_replace('\'', '\\\'', $error) }}',
        maxBytes: {{ $maxBytes ?: 'null' }},
        multiple: {{ $multiple ? 'true' : 'false' }},
        previewEnabled: {{ $preview ? 'true' : 'false' }},
        
        handleDrop(e) {
            this.isDropping = false;
            if (e.dataTransfer.files.length > 0) {
                this.validateAndSetFiles(e.dataTransfer.files);
            }
        },
        
        handleChange(e) {
            if (e.target.files.length > 0) {
                this.validateAndSetFiles(e.target.files);
            } else {
                this.files = [];
                this.clearPreviews();
            }
        },
        
        validateAndSetFiles(fileList) {
            this.errorMsg = null;
            let validFiles = Array.from(fileList);
            
            if (!this.multiple && validFiles.length > 1) {
                validFiles = [validFiles[0]];
            }
            
            if (this.maxBytes) {
                const oversized = validFiles.find(f => f.size > this.maxBytes);
                if (oversized) {
                    this.errorMsg = 'File exceeds maximum size of {{ str_replace('\'', '\\\'', (string)$maxSize) }}';
                    const dt = new DataTransfer();
                    this.$refs.input.files = dt.files;
                    this.files = [];
                    this.clearPreviews();
                    return;
                }
            }
            
            this.files = validFiles;
            
            if (this.previewEnabled) {
                this.clearPreviews();
                this.files.forEach(file => {
                    if (file.type.startsWith('image/')) {
                        const url = URL.createObjectURL(file);
                        this.previewUrls.push({ name: file.name, url: url, isImage: true });
                    } else {
                        this.previewUrls.push({ name: file.name, isImage: false });
                    }
                });
            }
        },
        
        clearPreviews() {
            this.previewUrls.forEach(p => {
                if (p.url) URL.revokeObjectURL(p.url);
            });
            this.previewUrls = [];
        }
    }"
    @dragover.prevent="isDropping = true"
    @dragleave.prevent="isDropping = false"
    @drop.prevent="handleDrop($event)"
>
    @if($label)
        <x-ui.label :for="$name">{{ $label }}</x-ui.label>
    @endif
    
    <div 
        class="w-full border-2 border-dashed rounded-lg p-6 flex flex-col items-center justify-center text-center transition-all duration-150 cursor-pointer relative overflow-hidden"
        :class="isDropping ? 'border-paw bg-paw-light' : (errorMsg ? 'border-rose bg-rose-light/20' : 'border-whisker bg-warm-white hover:bg-cream')"
        @click="$refs.input.click()"
    >
        <input 
            x-ref="input"
            type="file" 
            name="{{ $name }}{{ $multiple ? '[]' : '' }}" 
            id="{{ $name }}"
            class="hidden"
            {{ $multiple ? 'multiple' : '' }}
            @if($accept) accept="{{ $accept }}" @endif
            @change="handleChange"
            {!! $attributes->except(['class']) !!}
        />
        
        <div class="mb-3 text-2xl p-3 bg-paw-light rounded-pill flex items-center justify-center pointer-events-none">
            🐾
        </div>
        
        <p class="text-sm font-medium text-bark mb-1">
            Drop files here or click to browse
        </p>
        <p class="text-xs text-fur">
            @if($accept) {{ str_replace(',', ', ', $accept) }} allowed. @endif
            @if($maxSize) Up to {{ $maxSize }}. @endif
        </p>
    </div>
    
    <!-- Previews -->
    <div x-show="previewUrls.length > 0" class="mt-3 flex flex-wrap gap-3" style="display: none;">
        <template x-for="(preview, index) in previewUrls" :key="index">
            <div class="relative w-24 h-24 rounded-md overflow-hidden border border-whisker bg-cream shrink-0 flex items-center justify-center">
                <template x-if="preview.isImage">
                    <img :src="preview.url" class="w-full h-full object-cover" />
                </template>
                <template x-if="!preview.isImage">
                    <div class="text-xs text-fur text-center px-2 word-break break-all">
                        <span x-text="preview.name" class="line-clamp-3"></span>
                    </div>
                </template>
            </div>
        </template>
    </div>
    
    <!-- File names fallback if no previews -->
    <div x-show="files.length > 0 && !previewEnabled" class="mt-2 text-sm text-bark" style="display: none;">
        <p class="font-medium mb-1" x-text="files.length + (files.length === 1 ? ' file selected:' : ' files selected:')"></p>
        <ul class="text-xs text-fur list-disc list-inside">
            <template x-for="(file, index) in files" :key="index">
                <li x-text="file.name" class="truncate"></li>
            </template>
        </ul>
    </div>
    
    <div x-show="errorMsg" class="text-xs text-rose flex items-center gap-1 mt-1" style="display: none;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 shrink-0">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
        </svg>
        <span x-text="errorMsg"></span>
    </div>
    
    @if(!$error && $hint)
        <p class="text-xs text-fur mt-1" x-show="!errorMsg">{{ $hint }}</p>
    @endif
</div>
