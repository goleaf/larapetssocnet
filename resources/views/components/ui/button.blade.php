@props([
'variant'=>'primary',
'size'=>'md',
'href'=> null,
'type'=>'button',
'disabled'=> false,
'icon'=> null,
'leadingIcon'=> null,
'trailingIcon'=> null,
'loading'=> false,
'full'=> false,
])

@php
 $isDisabled = (bool) $disabled || (bool) $loading;

 $baseClasses = 'btn-base focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-60';

 $variants = [
'default' => 'border border-whisker/50 bg-warm-white text-bark shadow-sm hover:bg-cream',
'primary' => 'bg-paw text-white shadow-button hover:bg-paw-dark',
'secondary' => 'bg-paw-light text-paw-dark hover:bg-orange-200',
'ghost' => 'border border-whisker/40 bg-transparent text-fur hover:bg-cream hover:text-bark',
'danger' => 'bg-rose text-white shadow-button hover:bg-red-700',
'success' => 'bg-leaf text-white shadow-button hover:bg-green-700',
'outline' => 'border border-whisker bg-transparent text-bark hover:bg-cream',
 ];

 $sizes = [
'xs' => 'h-[var(--control-height-sm)] px-2.5 text-xs',
'sm' => 'h-[var(--control-height-sm)] px-3 text-sm',
'md' => 'h-[var(--control-height-md)] px-4 text-sm',
'lg' => 'h-[var(--control-height-lg)] px-5 text-base',
 ];

 $resolvedVariant = $variants[$variant] ?? $variants['primary'];
 $resolvedSize = $sizes[$size] ?? $sizes['md'];

 $classes = \Illuminate\Support\Arr::toCssClasses([
 $baseClasses,
 $resolvedVariant,
 $resolvedSize,
'w-full'=> $full,
'pointer-events-none'=> $isDisabled,
 ]);

 $renderIcon = static function (mixed $rawIcon): ?string {
 if ($rawIcon === null) {
 return null;
 }

 $iconString = trim((string) $rawIcon);

 if ($iconString ==='') {
 return null;
 }

 if (str_contains($iconString,'<svg')) {
 return $iconString;
 }

 if (str_contains($iconString,'<path')) {
 return'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4">'.$iconString.'</svg>';
 }

 return'<span class="leading-none">'.$iconString.'</span>';
 };

 $leadingIconMarkup = $renderIcon($leadingIcon ?? $icon);
 $trailingIconMarkup = $renderIcon($trailingIcon);
@endphp

@if ($href)
 <a
 href="{{ $isDisabled ?'#': $href }}"
 {{ $attributes->merge(['class'=> $classes,'data-ui-control'=>'button']) }}
 @if ($isDisabled)
 aria-disabled="true"
 tabindex="-1"
 @endif
 @if ($loading)
 aria-busy="true"
 @endif
 >
@else
 <button
 type="{{ $type }}"
 {{ $attributes->merge(['class'=> $classes,'data-ui-control'=>'button']) }}
 @if ($isDisabled)
 disabled
 @endif
 @if ($loading)
 aria-busy="true"
 @endif
 >
@endif
 @if ($loading)
 <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
 <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
 <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
 </svg>
 @elseif ($leadingIconMarkup)
 {!! $leadingIconMarkup !!}
 @endif

 {{ $slot }}

 @if (! $loading && $trailingIconMarkup)
 {!! $trailingIconMarkup !!}
 @endif
@if ($href)
 </a>
@else
 </button>
@endif
