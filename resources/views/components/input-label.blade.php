@props(['value'])

<label {{ $attributes->merge(['class' => 'mb-1 block text-sm font-semibold shell-text-muted']) }}>
    {{ $value ?? $slot }}
</label>
