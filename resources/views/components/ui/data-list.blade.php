@props([
    'items' => [],
    'divided' => true,
])

<dl {{ $attributes->class(['text-sm']) }}>
    @foreach ($items as $item)
         <div @class([
            'flex justify-between gap-4 py-2.5',
            'border-b border-whisker/20' => $divided && !$loop->last,
        ])>
                    <dt class="text-xs text-fur font-medium shrink-0">{{ $item['label'] ?? '' }}</dt>
                    <dd class="text-sm text-bark font-medium text-right">{{ $item['value'] ?? '—' }}</dd>
                </div>
    @endforeach
</dl>
