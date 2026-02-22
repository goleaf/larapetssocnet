@props([
    'for' => null,
    'required' => false,
])

<label
    @if (filled($for)) for="{{ $for }}" @endif
    {{ $attributes->class(['text-sm font-medium text-bark font-body']) }}
>
    {{ $slot }}
    @if ($required)
        <span class="text-rose ml-0.5" aria-hidden="true">*</span>
    @endif
</label>
