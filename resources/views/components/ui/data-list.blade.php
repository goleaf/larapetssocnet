@props([
'items'=> [],
'divided'=> false,
])

<dl {{ $attributes->merge(['class'=>'space-y-4']) }}>
 @foreach($items as $item)
 <div class="{{ $divided && ! $loop->last ?'border-b border-whisker/30 pb-4':''}}">
 <dt class="mb-0.5 text-xs font-medium text-fur">{{ data_get($item, 'label', data_get($item, 'key', data_get($item, 'title', ''))) }}</dt>
 <dd class="text-sm font-medium text-bark">{{ data_get($item, 'value', data_get($item, 'content', '')) }}</dd>
 </div>
 @endforeach

 {{ $slot }}
</dl>
