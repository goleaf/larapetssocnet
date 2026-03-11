@props([
'label',
'value',
'icon'=> null,
'trend'=> null,
'trendValue'=> null,
'trendDirection'=> null,
'trendUp'=> true,
])

@php
 $trendToken = is_string($trend) ? \Illuminate\Support\Str::of($trend)->trim()->lower()->value() : null;

 $resolvedDirection = match ($trendDirection ?? $trendToken) {
'up','increase','positive'=>'up',
'down','decrease','negative'=>'down',
 default => $trendUp ?'up':'down',
 };

 $resolvedTrend = $trendValue;

 if ($resolvedTrend === null && filled($trendToken) && ! in_array($trendToken, ['up','down','increase','decrease','positive','negative'], true)) {
 $resolvedTrend = $trend;
 }

 $showTrend = filled($resolvedTrend);
@endphp

<div {{ $attributes->merge(['class'=>'flex items-start gap-4 rounded-lg border border-whisker/30 bg-warm-white p-4 shadow-sm']) }}>
 @if($icon)
 <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-pill bg-paw-light text-xl text-paw-dark">
 {{ $icon }}
 </div>
 @endif

 <div class="min-w-0 flex-1">
 <p class="text-sm font-medium text-fur">{{ $label }}</p>

 <div class="mt-1 flex flex-wrap items-center gap-2">
 <p class="text-2xl font-bold font-display text-bark">{{ $value }}</p>

 @if($showTrend)
 <x-ui.badge size="sm":variant="$resolvedDirection ==='up'?'success':'danger'"class="font-mono">
 <span class="mr-1"aria-hidden="true">{{ $resolvedDirection ==='up'?'▲':'▼'}}</span>{{ $resolvedTrend }}
 </x-ui.badge>
 @endif
 </div>
 </div>
</div>
