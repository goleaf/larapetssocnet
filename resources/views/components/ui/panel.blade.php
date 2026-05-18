@props([
'title'=> null,
'subtitle'=> null,
'description'=> null,
'collapsible'=> false,
'open'=> true,
'padding'=>'md',
])

<div
 {{ $attributes->merge(['class'=>'ui-panel overflow-hidden']) }}
 x-data="{ open: {{ $open ?'true':'false'}} }"
>
 @if(isset($header))
 {{ $header }}
 @elseif(filled($title) || filled($description ?? $subtitle) || $collapsible)
 <div
 class="flex items-center justify-between border-b border-[var(--border-soft)] px-6 py-4 transition-colors {{ $collapsible ?'cursor-pointer hover:bg-cream':''}}"
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

 @if(filled($description ?? $subtitle))
 <p class="mt-0.5 text-xs text-fur">{{ $description ?? $subtitle }}</p>
 @endif
 </div>

 @if($collapsible)
 <div class="shrink-0 text-whisker transition-transform duration-200" :class="open ?'rotate-180':''">
 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
 <path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 0 1-1.06-.02L10 8.832 6.29 12.77a.75.75 0 1 1-1.08-1.04l4.25-4.5a.75.75 0 0 1 1.08 0l4.25 4.5a.75.75 0 0 1-.02 1.06z" clip-rule="evenodd"/>
 </svg>
 </div>
 @endif
 </div>
 @endif

 <div
 class="{{ ['none' => '', '0' => '', 'sm' => 'p-4', 'md' => 'p-6', 'lg' => 'p-8'][(string) $padding] ?? 'p-6' }}"
 @if($collapsible)
 x-show="open"
 x-cloak
 style="display: none;"
 @endif
 >
 {{ $slot }}
 </div>

 @isset($footer)
 <div class="border-t border-[var(--border-soft)] bg-[color:var(--surface-page)] px-6 py-4">
 {{ $footer }}
 </div>
 @endisset
</div>
