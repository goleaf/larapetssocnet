@props([
'title'=> null,
'subtitle'=> null,
'description'=> null,
'collapsible'=> false,
'open'=> true,
'padding'=>'md',
])

@php
 $summary = $description ?? $subtitle;

 $paddings = [
'none'=>'',
'0'=>'',
'sm'=>'p-3',
'md'=>'p-4',
'lg'=>'p-6',
 ];

 $paddingClass = $paddings[(string) $padding] ?? $paddings['md'];
@endphp

<div
 {{ $attributes->merge(['class'=>'overflow-hidden rounded-lg border border-whisker/30 bg-warm-white shadow-card']) }}
 x-data="{ open: {{ $open ?'true':'false'}} }"
>
 @if(isset($header))
 {{ $header }}
 @elseif(filled($title) || filled($summary) || $collapsible)
 <div
 class="flex items-center justify-between border-b border-whisker/40 px-4 py-3 transition-colors {{ $collapsible ?'cursor-pointer hover:bg-cream':''}}"
 @if($collapsible)
 @click="open = !open"
 :aria-expanded="open.toString()"
 role="button"
 tabindex="0"
 @keydown.enter.prevent="open = !open"
 @keydown.space.prevent="open = !open"
 @endif
 >
 <div>
 @if(filled($title))
 <h4 class="font-semibold font-display text-bark">{{ $title }}</h4>
 @endif

 @if(filled($summary))
 <p class="mt-0.5 text-xs text-fur">{{ $summary }}</p>
 @endif
 </div>

 @if($collapsible)
 <div class="shrink-0 text-whisker transition-transform duration-200":class="open ?'rotate-180':''">
 <svg xmlns="http://www.w3.org/2000/svg"viewBox="0 0 20 20"fill="currentColor"class="h-5 w-5">
 <path fill-rule="evenodd"d="M14.77 12.79a.75.75 0 0 1-1.06-.02L10 8.832 6.29 12.77a.75.75 0 1 1-1.08-1.04l4.25-4.5a.75.75 0 0 1 1.08 0l4.25 4.5a.75.75 0 0 1-.02 1.06z"clip-rule="evenodd"/>
 </svg>
 </div>
 @endif
 </div>
 @endif

 <div
 class="{{ $paddingClass }}"
 @if($collapsible)
 x-show="open"
 x-collapse
 x-cloak
 style="display: none;"
 @endif
 >
 {{ $slot }}
 </div>

 @isset($footer)
 <div class="border-t border-whisker/40 bg-cream/40 px-4 py-3">
 {{ $footer }}
 </div>
 @endisset
</div>
