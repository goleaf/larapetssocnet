@props([
'variant'=>'default',
'tone'=> null,
'size'=>'md',
'dot'=> false,
'pill'=> true,
'icon'=> null,
])

@php
 $resolvedVariant = $tone ?: $variant;

 $baseClasses ='inline-flex items-center justify-center gap-1.5 font-medium whitespace-nowrap';

 $variants = [
'default'=>'bg-cream text-fur border border-whisker',
'neutral'=>'bg-cream text-fur border border-whisker',
'primary'=>'bg-paw-light text-paw-dark border border-paw-light',
'secondary'=>'bg-sky-light text-sky border border-sky-light',
'success'=>'bg-leaf-light text-leaf border border-leaf-light',
'danger'=>'bg-rose-light text-rose border border-rose-light',
'warning'=>'bg-amber-light text-amber border border-amber-light',
'info'=>'bg-sky-light text-sky border border-sky-light',
'dark'=>'bg-bark text-cream border border-bark',
 ];

 $sizes = [
'sm'=>'px-2 py-0.5 text-[11px]',
'md'=>'px-2.5 py-1 text-xs',
'lg'=>'px-3 py-1.5 text-sm',
 ];

 $dotColors = [
'default'=>'bg-fur',
'neutral'=>'bg-fur',
'primary'=>'bg-paw-dark',
'secondary'=>'bg-sky',
'success'=>'bg-leaf',
'danger'=>'bg-rose',
'warning'=>'bg-amber',
'info'=>'bg-sky',
'dark'=>'bg-cream',
 ];

 $iconString = trim((string) ($icon ??''));

 if ($iconString !==''&& str_contains($iconString,'<path')) {
 $iconString ='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5">'.$iconString.'</svg>';
 }

 $classes = \Illuminate\Support\Arr::toCssClasses([
 $baseClasses,
 $variants[$resolvedVariant] ?? $variants['default'],
 $sizes[$size] ?? $sizes['md'],
 $pill ?'rounded-pill':'rounded-md',
 ]);
@endphp

<span {{ $attributes->merge(['class'=> $classes]) }}>
 @if ($dot)
 <span class="h-1.5 w-1.5 rounded-pill {{ $dotColors[$resolvedVariant] ??'bg-fur'}}"></span>
 @endif

 @if ($iconString !=='')
 {!! $iconString !!}
 @endif

 {{ $slot }}
</span>
