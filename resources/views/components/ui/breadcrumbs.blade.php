@props([
'items'=> [],
'links'=> [],
])

@php
 $resolvedItems = ! empty($items) ? $items : $links;
@endphp

<nav {{ $attributes->merge(['class'=>'flex']) }} aria-label="Breadcrumb">
 <ol role="list" class="flex items-center space-x-2">
 @foreach($resolvedItems as $index => $item)
 @php
 $isLast = $index === count($resolvedItems) - 1;

 if (is_array($item)) {
 $label = $item['label'] ??'';
 $href = $item['href'] ?? $item['url'] ?? null;

 if (! $href && ! empty($item['route']) && Route::has($item['route'])) {
 $href = route($item['route'], $item['params'] ?? []);
 }
 } else {
 $label = $item;
 $href = null;
 }
 @endphp

 <li>
 <div class="flex items-center">
 @if($index > 0)
 <svg class="mr-2 h-4 w-4 shrink-0 text-whisker" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
 <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02z" clip-rule="evenodd" />
 </svg>
 @endif

 @if($href && ! $isLast)
 <a href="{{ $href }}" class="text-sm font-medium text-fur transition-colors hover:text-bark">{{ $label }}</a>
 @else
 <span class="text-sm font-medium {{ $isLast ?'text-bark':'text-fur'}}"@if($isLast) aria-current="page"@endif>{{ $label }}</span>
 @endif
 </div>
 </li>
 @endforeach
 </ol>
</nav>
