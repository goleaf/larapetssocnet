@props([
'value'=> 0,
'max'=> 100,
'label'=> null,
'color'=>'paw',
'showValue'=> true,
])

@php
 $maxValue = max(1, (float) $max);
 $rawValue = max(0, (float) $value);
 $normalizedValue = min($rawValue, $maxValue);
 $percentage = (int) round(($normalizedValue / $maxValue) * 100);

 $colorAliases = [
'success'=>'leaf',
'error'=>'rose',
'danger'=>'rose',
'warning'=>'amber',
'info'=>'sky',
 ];

 $colorKey = $colorAliases[(string) $color] ?? (string) $color;

 $colors = [
'paw'=>'bg-paw',
'leaf'=>'bg-leaf',
'sky'=>'bg-sky',
'rose'=>'bg-rose',
'amber'=>'bg-amber',
 ];

 $colorClass = $colors[$colorKey] ?? $colors['paw'];
@endphp

<div {{ $attributes->merge(['class'=>'w-full']) }}>
 @if(filled($label) || $showValue)
 <div class="mb-1.5 flex items-end justify-between gap-3">
 @if(filled($label))
 <span class="text-sm font-medium text-bark">{{ $label }}</span>
 @else
 <span></span>
 @endif

 @if($showValue)
 <span class="text-xs font-medium text-fur">{{ $percentage }}%</span>
 @endif
 </div>
 @endif

 <div class="h-2 w-full overflow-hidden rounded-pill bg-whisker/30">
 <div class="h-full rounded-pill {{ $colorClass }} transition-all duration-500 ease-out" style="width: {{ $percentage }}%"></div>
 </div>
</div>
