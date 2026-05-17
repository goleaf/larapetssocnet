@props([
'items'=> [],
'title'=> null,
])

<div {{ $attributes->merge(['class'=>'w-full','data-ui'=>'sidebar-nav']) }}>
 @if(filled($title))
 <h4 class="mb-2 px-3 text-xs font-bold font-display uppercase tracking-wider text-fur">{{ $title }}</h4>
 @endif

 <nav class="space-y-1" aria-label="Sidebar">
 @foreach($items as $item)

 <a
 href="{{ (($item['href'] ?? $item['url'] ?? '#') === '#' && ! empty($item['route']) && Route::has($item['route'])) ? route($item['route']) : ($item['href'] ?? $item['url'] ?? '#') }}"
 class="flex min-h-11 items-center justify-between rounded-[var(--radius-soft)] px-3 py-2 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw {{ (array_key_exists('active', $item) ? (bool) $item['active'] : (
    request()->url() === url((($item['href'] ?? $item['url'] ?? '#') === '#' && ! empty($item['route']) && Route::has($item['route']) ? route($item['route']) : ($item['href'] ?? $item['url'] ?? '#')))
    || collect(\Illuminate\Support\Arr::wrap($item['pattern'] ?? $item['patterns'] ?? []))->contains(fn ($pattern) => (Route::currentRouteName() && \Illuminate\Support\Str::is((string) $pattern, Route::currentRouteName())) || request()->is((string) $pattern))
    || (Route::currentRouteName() && collect(\Illuminate\Support\Arr::wrap($item['routePattern'] ?? $item['routePatterns'] ?? []))->contains(fn ($pattern) => \Illuminate\Support\Str::is((string) $pattern, Route::currentRouteName())))
 )) ?'bg-paw-light font-medium text-paw-dark':'text-fur hover:bg-cream hover:text-bark'}}"
 @if(array_key_exists('active', $item) ? (bool) $item['active'] : (
    request()->url() === url((($item['href'] ?? $item['url'] ?? '#') === '#' && ! empty($item['route']) && Route::has($item['route']) ? route($item['route']) : ($item['href'] ?? $item['url'] ?? '#')))
    || collect(\Illuminate\Support\Arr::wrap($item['pattern'] ?? $item['patterns'] ?? []))->contains(fn ($pattern) => (Route::currentRouteName() && \Illuminate\Support\Str::is((string) $pattern, Route::currentRouteName())) || request()->is((string) $pattern))
    || (Route::currentRouteName() && collect(\Illuminate\Support\Arr::wrap($item['routePattern'] ?? $item['routePatterns'] ?? []))->contains(fn ($pattern) => \Illuminate\Support\Str::is((string) $pattern, Route::currentRouteName())))
 ))
 aria-current="page"
 @endif
 >
 <span class="flex min-w-0 items-center gap-3">
 @if($item['icon'] ?? null)
 <span class="shrink-0 {{ (array_key_exists('active', $item) ? (bool) $item['active'] : (
    request()->url() === url((($item['href'] ?? $item['url'] ?? '#') === '#' && ! empty($item['route']) && Route::has($item['route']) ? route($item['route']) : ($item['href'] ?? $item['url'] ?? '#')))
    || collect(\Illuminate\Support\Arr::wrap($item['pattern'] ?? $item['patterns'] ?? []))->contains(fn ($pattern) => (Route::currentRouteName() && \Illuminate\Support\Str::is((string) $pattern, Route::currentRouteName())) || request()->is((string) $pattern))
    || (Route::currentRouteName() && collect(\Illuminate\Support\Arr::wrap($item['routePattern'] ?? $item['routePatterns'] ?? []))->contains(fn ($pattern) => \Illuminate\Support\Str::is((string) $pattern, Route::currentRouteName())))
 )) ?'text-paw':'text-whisker'}}">{!! $item['icon'] !!}</span>
 @endif

 <span class="truncate">{{ $item['label'] ?? '' }}</span>
 </span>

 @if(($item['badge'] ?? $item['count'] ?? null) !== null)
 <x-ui.badge :variant="$item['badgeVariant'] ?? ((array_key_exists('active', $item) ? (bool) $item['active'] : (
    request()->url() === url((($item['href'] ?? $item['url'] ?? '#') === '#' && ! empty($item['route']) && Route::has($item['route']) ? route($item['route']) : ($item['href'] ?? $item['url'] ?? '#')))
    || collect(\Illuminate\Support\Arr::wrap($item['pattern'] ?? $item['patterns'] ?? []))->contains(fn ($pattern) => (Route::currentRouteName() && \Illuminate\Support\Str::is((string) $pattern, Route::currentRouteName())) || request()->is((string) $pattern))
    || (Route::currentRouteName() && collect(\Illuminate\Support\Arr::wrap($item['routePattern'] ?? $item['routePatterns'] ?? []))->contains(fn ($pattern) => \Illuminate\Support\Str::is((string) $pattern, Route::currentRouteName())))
 )) ? 'primary' : 'default')" size="sm">
 {{ $item['badge'] ?? $item['count'] ?? null }}
 </x-ui.badge>
 @endif
 </a>
 @endforeach
 </nav>
</div>
