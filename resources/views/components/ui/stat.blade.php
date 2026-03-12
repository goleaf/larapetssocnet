@props([
'label',
'value',
'icon'=> null,
'trend'=> null,
'trendValue'=> null,
'trendDirection'=> null,
'trendUp'=> true,
])

<div {{ $attributes->merge(['class'=>'shell-card flex items-start gap-4 p-4']) }}>
 @if($icon)
 <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-soft)] bg-paw-light text-xl text-paw-dark">
 {{ $icon }}
 </div>
 @endif

 <div class="min-w-0 flex-1">
 <p class="text-sm font-medium text-fur">{{ $label }}</p>

 <div class="mt-1 flex flex-wrap items-center gap-2">
 <p class="text-2xl font-bold font-display text-bark">{{ $value }}</p>

 @if(filled($trendValue ?? (filled(is_string($trend) ? \Illuminate\Support\Str::of($trend)->trim()->lower()->value() : null) && ! in_array(is_string($trend) ? \Illuminate\Support\Str::of($trend)->trim()->lower()->value() : null, ['up', 'down', 'increase', 'decrease', 'positive', 'negative'], true) ? $trend : null)))
 <x-ui.badge size="sm" :variant="match ($trendDirection ?? (is_string($trend) ? \Illuminate\Support\Str::of($trend)->trim()->lower()->value() : null)) {
 'up', 'increase', 'positive' => 'success',
 'down', 'decrease', 'negative' => 'danger',
 default => ($trendUp ? 'success' : 'danger'),
}" class="font-mono">
 <span class="mr-1" aria-hidden="true">{{ match ($trendDirection ?? (is_string($trend) ? \Illuminate\Support\Str::of($trend)->trim()->lower()->value() : null)) {
 'up', 'increase', 'positive' => '▲',
 'down', 'decrease', 'negative' => '▼',
 default => ($trendUp ? '▲' : '▼'),
} }}</span>{{ $trendValue ?? (filled(is_string($trend) ? \Illuminate\Support\Str::of($trend)->trim()->lower()->value() : null) && ! in_array(is_string($trend) ? \Illuminate\Support\Str::of($trend)->trim()->lower()->value() : null, ['up', 'down', 'increase', 'decrease', 'positive', 'negative'], true) ? $trend : null) }}
 </x-ui.badge>
 @endif
 </div>
 </div>
</div>
