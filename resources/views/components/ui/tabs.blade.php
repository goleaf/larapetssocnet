@props([
'tabs'=> [],
'active'=> null,
'paramName'=>'tab',
'animatedIndicator'=> false,
])

@php
 $activeParam = $active ?? request()->query($paramName);

 $normalizedTabs = collect($tabs)->map(function ($tab) use ($activeParam, $paramName) {
 $isArray = is_array($tab);

 $value = $isArray
 ? ($tab['value'] ?? $tab['id'] ?? $tab['key'] ?? $tab['label'] ?? null)
 : $tab;

 $label = $isArray
 ? ($tab['label'] ?? $tab['title'] ?? $value)
 : $tab;

 $href = $isArray ? ($tab['href'] ?? $tab['url'] ?? null) : null;
 $count = $isArray ? ($tab['count'] ?? null) : null;

 $isActive = $isArray && array_key_exists('active', $tab)
 ? (bool) $tab['active']
 : ((string) $activeParam !==''&& (string) $activeParam === (string) $value);

 if (! $href) {
 if ($value === null || $value ==='') {
 $href ='#';
 } else {
 $href = request()->fullUrlWithQuery([$paramName => $value,'page'=> null]);
 }
 }

 return [
'label'=> $label,
'value'=> $value,
'href'=> $href,
'count'=> $count,
'active'=> $isActive,
 ];
 })->values();

 if ($activeParam === null && $normalizedTabs->isNotEmpty()) {
 $firstValue = $normalizedTabs->first()['value'];
 $normalizedTabs = $normalizedTabs->map(function (array $tab, int $index) use ($firstValue) {
 $tab['active'] = $index === 0 || ((string) $tab['value'] !==''&& (string) $tab['value'] === (string) $firstValue);
 return $tab;
 });
 }
@endphp

<div {{ $attributes->merge(['class'=>'relative mb-6 w-full border-b border-whisker/40 [--profile-tab-indicator-left:0px] [--profile-tab-indicator-width:0px]','data-ui'=>'tabs']) }}>
 <nav
 class="relative -mb-px flex gap-6 overflow-x-auto no-scrollbar"
 aria-label="Tabs"
 @if($animatedIndicator)
 x-ref="tabNav"
 @endif
 >
 @foreach($normalizedTabs as $tab)
 @php
 $inactiveClasses = $animatedIndicator
 ? 'border-transparent text-fur hover:text-bark'
 : 'border-transparent text-fur hover:border-whisker hover:text-bark';
 $activeClasses = $animatedIndicator
 ? 'border-transparent font-semibold text-paw'
 : 'border-paw font-semibold text-paw';
 @endphp
 <a
 href="{{ $tab['href'] }}"
 data-tab-value="{{ $tab['value'] }}"
 class="flex min-h-11 items-center gap-2 whitespace-nowrap rounded-t-[var(--radius-soft)] border-b-2 px-1 py-3 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw {{ $tab['active'] ? $activeClasses : $inactiveClasses }}"
 @if($tab['active'])
 aria-current="page"
 @endif
 >
 <span data-tab-indicator-anchor>{{ $tab['label'] }}</span>

 @if($tab['count'] !== null)
 <x-ui.badge :variant="$tab['active'] ?'primary':'default'" size="sm" class="ml-1.5">
 {{ $tab['count'] }}
 </x-ui.badge>
 @endif
 </a>
 @endforeach

 @if($animatedIndicator)
 <span
 data-ui="tabs-active-indicator"
 aria-hidden="true"
 style="background-color: var(--profile-theme-tab-underline, var(--color-paw));"
 class="pointer-events-none absolute bottom-0 h-0.5 rounded-full bg-paw transition-[left,width] duration-200 ease-in-out motion-reduce:transition-none [left:var(--profile-tab-indicator-left)] [width:var(--profile-tab-indicator-width)]"></span>
 @endif
 </nav>
</div>
