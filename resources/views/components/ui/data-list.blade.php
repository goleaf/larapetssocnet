@props([
'items'=> [],
'divided'=> false,
])

<dl {{ $attributes->merge(['class'=>'space-y-4']) }}>
 @foreach($items as $item)
 @php
 $label = $item['label'] ?? $item['key'] ?? $item['title'] ??'';
 $value = $item['value'] ?? $item['content'] ??'';
 @endphp

 <div class="{{ $divided && ! $loop->last ?'border-b border-whisker/30 pb-4':''}}">
 <dt class="mb-0.5 text-xs font-medium text-fur">{{ $label }}</dt>
 <dd class="text-sm font-medium text-bark">{{ $value }}</dd>
 </div>
 @endforeach

 {{ $slot }}
</dl>
