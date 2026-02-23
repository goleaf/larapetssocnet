@props([
'text',
'position'=>'top',
])

@php
 $positionClasses = match ((string) $position) {
'bottom'=>'left-1/2 top-full mt-2 -translate-x-1/2',
'left'=>'right-full top-1/2 mr-2 -translate-y-1/2',
'right'=>'left-full top-1/2 ml-2 -translate-y-1/2',
 default =>'bottom-full left-1/2 mb-2 -translate-x-1/2',
 };

 $arrowClasses = match ((string) $position) {
'bottom'=>'bottom-full left-1/2 -translate-x-1/2 border-b-bark border-x-transparent border-t-transparent border-[6px]',
'left'=>'left-full top-1/2 -translate-y-1/2 border-l-bark border-y-transparent border-r-transparent border-[6px]',
'right'=>'right-full top-1/2 -translate-y-1/2 border-r-bark border-y-transparent border-l-transparent border-[6px]',
 default =>'left-1/2 top-full -translate-x-1/2 border-t-bark border-x-transparent border-b-transparent border-[6px]',
 };
@endphp

@if($slot->isNotEmpty())
 <div
 class="relative inline-block"
 x-data="{ open: false }"
 @mouseenter="open = true"
 @mouseleave="open = false"
 @focusin="open = true"
 @focusout="open = false"
 @keydown.escape.window="open = false"
 >
 {{ $slot }}

 <div
 x-show="open"
 x-cloak
 style="display: none;"
 x-transition:enter="transition ease-out duration-150"
 x-transition:enter-start="opacity-0 scale-95"
 x-transition:enter-end="opacity-100 scale-100"
 x-transition:leave="transition ease-in duration-100"
 x-transition:leave-start="opacity-100 scale-100"
 x-transition:leave-end="opacity-0 scale-95"
 class="pointer-events-none absolute z-50 {{ $positionClasses }} whitespace-nowrap"
 >
 <div class="rounded-md bg-bark px-2.5 py-1.5 text-xs font-medium text-cream shadow-md">
 {{ $text }}
 </div>
 <div class="absolute h-0 w-0 {{ $arrowClasses }}"></div>
 </div>
 </div>
@else
 <span
 class="pointer-events-none absolute z-50"
 x-data="{ open: false }"
 x-init="
 const parent = $el.parentElement;
 if (!parent) {
 return;
 }

 parent.addEventListener('mouseenter', () => open = true);
 parent.addEventListener('mouseleave', () => open = false);
 parent.addEventListener('focusin', () => open = true);
 parent.addEventListener('focusout', () => open = false);
"
 @keydown.escape.window="open = false"
 >
 <span
 x-show="open"
 x-cloak
 style="display: none;"
 x-transition:enter="transition ease-out duration-150"
 x-transition:enter-start="opacity-0 scale-95"
 x-transition:enter-end="opacity-100 scale-100"
 x-transition:leave="transition ease-in duration-100"
 x-transition:leave-start="opacity-100 scale-100"
 x-transition:leave-end="opacity-0 scale-95"
 class="pointer-events-none absolute {{ $positionClasses }} whitespace-nowrap"
 >
 <span class="rounded-md bg-bark px-2.5 py-1.5 text-xs font-medium text-cream shadow-md">{{ $text }}</span>
 <span class="absolute h-0 w-0 {{ $arrowClasses }}"></span>
 </span>
 </span>
@endif
