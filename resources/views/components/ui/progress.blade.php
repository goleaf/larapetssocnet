@props([
'value'=> 0,
'max'=> 100,
'label'=> null,
'color'=>'paw',
'showValue'=> true,
])

<div {{ $attributes->merge(['class'=>'w-full']) }}>
 @if(filled($label) || $showValue)
 <div class="mb-1.5 flex items-end justify-between gap-3">
 @if(filled($label))
 <span class="text-sm font-medium text-bark">{{ $label }}</span>
 @else
 <span></span>
 @endif

 @if($showValue)
 <span class="text-xs font-medium text-fur">{{ (int) round((min(max(0, (float) $value), max(1, (float) $max)) / max(1, (float) $max)) * 100) }}%</span>
 @endif
 </div>
 @endif

 <div class="h-2 w-full overflow-hidden rounded-pill bg-whisker/30">
 <div class="h-full rounded-pill {{ match (['success' => 'leaf', 'error' => 'rose', 'danger' => 'rose', 'warning' => 'amber', 'info' => 'sky'][(string) $color] ?? (string) $color) {
 'leaf' => 'bg-leaf',
 'sky' => 'bg-sky',
 'rose' => 'bg-rose',
 'amber' => 'bg-amber',
 default => 'bg-paw',
 } }} transition-all duration-500 ease-out" style="width: {{ (int) round((min(max(0, (float) $value), max(1, (float) $max)) / max(1, (float) $max)) * 100) }}%"></div>
 </div>
</div>
