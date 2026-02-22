@props([
    'items' => [], /* array of ['label', 'value'] */
    'divided' => false,
])

<dl {{ $attributes->merge(['class' => 'space-y-4']) }}>
    @foreach($items as $item)
        @php
            $label = $item['label'] ?? '';
            $value = $item['value'] ?? '';
        @endphp
        <div class="{{ $divided && !$loop->last ? 'pb-4 border-b border-whisker/30' : '' }}">
                <dt class="text-xs text-fur font-medium mb-0.5">{{ $label }}</dt>
            <dd class="text-sm text-bark font-medium">{{ $value }}</dd>
            </div>
    @endforeach
    
    {{ $slot }}
</dl>
