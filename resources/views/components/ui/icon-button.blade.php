@props([
'variant'=>'ghost',
'size'=>'md',
'href'=> null,
'type'=>'button',
'disabled'=> false,
'icon'=> null,
'pill'=> true,
'label'=> null,
])

@php
 $isDisabled = (bool) $disabled;

 $baseClasses ='inline-flex items-center justify-center aspect-square transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw';

 $variants = [
'default'=>'bg-warm-white text-bark border border-whisker/50 hover:bg-cream shadow-sm',
'primary'=>'bg-paw text-white hover:bg-paw-dark shadow-button',
'secondary'=>'bg-paw-light text-paw-dark hover:bg-orange-200',
'ghost'=>'bg-transparent text-fur hover:bg-cream border border-whisker/40',
'danger'=>'bg-rose text-white hover:bg-red-700 shadow-button',
'success'=>'bg-leaf text-white hover:bg-green-700 shadow-button',
'outline'=>'bg-transparent text-bark border border-whisker hover:bg-cream',
 ];

 $sizes = [
'xs'=>'w-6 h-6 text-xs',
'sm'=>'w-8 h-8 text-sm',
'md'=>'w-10 h-10 text-base',
'lg'=>'w-12 h-12 text-lg',
 ];

 $classes = \Illuminate\Support\Arr::toCssClasses([
 $baseClasses,
 $variants[$variant] ?? $variants['ghost'],
 $sizes[$size] ?? $sizes['md'],
 $pill ?'rounded-pill':'rounded-md',
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
 {{ $attributes->merge(['class'=> $classes]) }}
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
 {{ $attributes->merge(['class'=> $classes]) }}
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
