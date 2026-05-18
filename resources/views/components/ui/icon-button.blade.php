@props([
'variant'=>'ghost',
'size'=>'md',
'href'=> null,
'type'=>'button',
'disabled'=> false,
'icon'=> null,
'label'=> null,
])

@php
 $isDisabled = (bool) $disabled;

 $baseClasses = 'icon-button shrink-0 transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw';

 $variants = [
'default' => 'btn-default',
'primary' => 'btn-primary',
'secondary' => 'btn-secondary',
'ghost' => 'btn-ghost',
'danger' => 'btn-danger',
'success' => 'btn-success',
'outline' => 'btn-outline',
 ];

 $sizes = [
'xs' => 'h-[var(--control-height-sm)] w-[var(--control-height-sm)] text-xs',
'sm' => 'h-[var(--control-height-sm)] w-[var(--control-height-sm)] text-sm',
'md' => 'h-[var(--control-height-md)] w-[var(--control-height-md)] text-base',
'lg' => 'h-[var(--control-height-lg)] w-[var(--control-height-lg)] text-lg',
 ];

 $classes = \Illuminate\Support\Arr::toCssClasses([
 $baseClasses,
 $variants[$variant] ?? $variants['ghost'],
 $sizes[$size] ?? $sizes['md'],
 'rounded-none',
'opacity-60 cursor-not-allowed pointer-events-none'=> $isDisabled,
 ]);

 $iconString = trim((string) ($icon ??''));

 if ($iconString !==''&& str_contains($iconString,'<path')) {
 $iconString ='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-[1em] w-[1em]">'.$iconString.'</svg>';
 }

 $ariaLabel = $attributes->get('aria-label') ?: $label;
@endphp

@if ($href)
 <a
 href="{{ $isDisabled ?'#': $href }}"
 {{ $attributes->merge(['class'=> $classes,'data-ui-control'=>'button']) }}
 @if ($isDisabled)
 aria-disabled="true"
 tabindex="-1"
 @endif
 @if ($ariaLabel)
 aria-label="{{ $ariaLabel }}"
 @endif
 >
@else
 <button
 type="{{ $type }}"
 {{ $attributes->merge(['class'=> $classes,'data-ui-control'=>'button']) }}
 @if ($isDisabled)
 disabled
 @endif
 @if ($ariaLabel)
 aria-label="{{ $ariaLabel }}"
 @endif
 >
@endif
 @if ($iconString !=='')
 {!! $iconString !!}
 @else
 {{ $slot }}
 @endif
@if ($href)
 </a>
@else
 </button>
@endif
