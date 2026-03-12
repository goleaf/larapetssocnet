@props([
'items'=> [],
'links'=> [],
])

<nav {{ $attributes->merge(['class'=>'flex']) }} aria-label="Breadcrumb">
 <ol role="list" class="flex items-center space-x-2">
 @foreach((! empty($items) ? $items : $links) as $index => $item)

 <li>
 <div class="flex items-center">
 @if($index > 0)
 <svg class="mr-2 h-4 w-4 shrink-0 text-whisker" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
 <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02z" clip-rule="evenodd"/>
 </svg>
 @endif

 @if((is_array($item)
    ? (($item['href'] ?? $item['url'] ?? null)
        ?: ((! empty($item['route']) && Route::has($item['route'])) ? route($item['route'], $item['params'] ?? []) : null))
    : null) && $index !== count((! empty($items) ? $items : $links)) - 1)
 <a href="{{ is_array($item)
    ? (($item['href'] ?? $item['url'] ?? null)
        ?: ((! empty($item['route']) && Route::has($item['route'])) ? route($item['route'], $item['params'] ?? []) : null))
    : null }}" class="text-sm font-medium text-fur transition-colors hover:text-bark">{{ is_array($item) ? ($item['label'] ?? '') : $item }}</a>
 @else
 <span class="text-sm font-medium {{ $index === count((! empty($items) ? $items : $links)) - 1 ?'text-bark':'text-fur'}}" @if($index === count((! empty($items) ? $items : $links)) - 1) aria-current="page" @endif>{{ is_array($item) ? ($item['label'] ?? '') : $item }}</span>
 @endif
 </div>
 </li>
 @endforeach
 </ol>
</nav>
