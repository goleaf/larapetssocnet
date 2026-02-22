@props(['for', 'required' => false])

<label for="{{ $for }}" {{ $attributes->merge(['class' => 'text-sm font-medium text-bark font-body block mb-1']) }}>
    {{ $slot }}
    @if($required)
        <span class="text-rose ml-0.5">*</span>
    @endif
</label>