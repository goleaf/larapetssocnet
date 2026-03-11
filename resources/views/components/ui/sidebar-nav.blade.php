@props([
'items'=> [],
'title'=> null,
])

@php
 $currentRoute = Route::currentRouteName();
@endphp

<div {{ $attributes->merge(['class'=>'w-full']) }}>
 @if(filled($title))
 <h4 class="mb-2 px-3 text-xs font-bold font-display uppercase tracking-wider text-fur">{{ $title }}</h4>
 @endif

 <nav class="space-y-1" aria-label="Sidebar">
 @foreach($items as $item)
 @php
 $href = $item['href'] ?? $item['url'] ??'#';

 if ($href ==='#'&& ! empty($item['route']) && Route::has($item['route'])) {
 $href = route($item['route']);
 }

 $label = $item['label'] ??'';
 $icon = $item['icon'] ?? null;
 $badge = $item['badge'] ?? $item['count'] ?? null;

 if (array_key_exists('active', $item)) {
 $isActive = (bool) $item['active'];
 } else {
 $patterns = \Illuminate\Support\Arr::wrap($item['pattern'] ?? $item['patterns'] ?? []);
 $routePatterns = \Illuminate\Support\Arr::wrap($item['routePattern'] ?? $item['routePatterns'] ?? []);

 $patternMatched = false;
 foreach ($patterns as $pattern) {
 $pattern = (string) $pattern;

 if ($currentRoute && \Illuminate\Support\Str::is($pattern, $currentRoute)) {
 $patternMatched = true;
 break;
 }

 if (request()->is($pattern)) {
 $patternMatched = true;
 break;
 }
 }

 $routeMatched = false;
 if ($currentRoute) {
 foreach ($routePatterns as $pattern) {
 if (\Illuminate\Support\Str::is((string) $pattern, $currentRoute)) {
 $routeMatched = true;
 break;
 }
 }
 }

 $urlMatched = request()->url() === url($href);
 $isActive = $urlMatched || $patternMatched || $routeMatched;
 }

 $badgeVariant = $item['badgeVariant'] ?? ($isActive ?'primary':'default');
 @endphp

 <a
 href="{{ $href }}"
 class="flex items-center justify-between rounded-lg px-3 py-2 text-sm transition-colors {{ $isActive ?'bg-paw-light font-medium text-paw-dark':'text-fur hover:bg-cream hover:text-bark'}}"
 @if($isActive)
 aria-current="page"
 @endif
 >
 <span class="flex min-w-0 items-center gap-3">
 @if($icon)
 <span class="shrink-0 {{ $isActive ?'text-paw':'text-whisker'}}">{!! $icon !!}</span>
 @endif

 <span class="truncate">{{ $label }}</span>
 </span>

 @if($badge !== null)
    <x-ui.badge :variant="$badgeVariant" size="sm" pill>
 {{ $badge }}
 </x-ui.badge>
 @endif
 </a>
 @endforeach
 </nav>
</div>
