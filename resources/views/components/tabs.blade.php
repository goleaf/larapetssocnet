@props([
'tabs'=> [],
'active'=> null,
])

@php
 $normalizedTabs = collect($tabs)->map(function ($tab, $key) {
 if (is_string($tab)) {
 return [
'id'=> is_string($key) ? $key : Illuminate\Support\Str::slug($tab),
'label'=> $tab,
'href'=> null,
'count'=> null,
 ];
 }

 if (is_array($tab)) {
 $label = $tab['label'] ?? (is_string($key) ? $key :'Tab');

 return [
'id'=> $tab['id'] ?? (is_string($key) ? $key : Illuminate\Support\Str::slug($label)),
'label'=> $label,
'href'=> $tab['href'] ?? null,
'count'=> $tab['count'] ?? null,
 ];
 }

 return [
'id'=> is_string($key) ? $key :'tab-'.$key,
'label'=>'Tab',
'href'=> null,
'count'=> null,
 ];
 })->values();

 $initialTab = $active ?? ($normalizedTabs->first()['id'] ?? null);
@endphp

<div {{ $attributes->merge(['class'=>'shell-card p-3']) }} x-data="tabsState(@js($initialTab))">
 <nav class="flex flex-wrap items-center gap-2"role="tablist"aria-label="Tabs Navigation">
 @foreach ($normalizedTabs as $tab)
 @if ($tab['href'])
 <a
 href="{{ $tab['href'] }}"
 @click="setTab('{{ $tab['id'] }}')"
 class="btn-base px-3 py-2 text-sm"
 :class="isTab('{{ $tab['id'] }}') ?'btn-primary':'btn-ghost'"
 role="tab"
 :aria-selected="isTab('{{ $tab['id'] }}').toString()"
 >
 <span>{{ $tab['label'] }}</span>
 @if (! is_null($tab['count']))
 <span class="rounded-full px-2 py-0.5 text-xs"style="background: color-mix(in srgb, var(--ui-surface) 70%, transparent);">{{ $tab['count'] }}</span>
 @endif
 </a>
 @else
 <button
 type="button"
 @click="setTab('{{ $tab['id'] }}')"
 class="btn-base px-3 py-2 text-sm"
 :class="isTab('{{ $tab['id'] }}') ?'btn-primary':'btn-ghost'"
 role="tab"
 :aria-selected="isTab('{{ $tab['id'] }}').toString()"
 >
 <span>{{ $tab['label'] }}</span>
 @if (! is_null($tab['count']))
 <span class="rounded-full px-2 py-0.5 text-xs"style="background: color-mix(in srgb, var(--ui-surface) 70%, transparent);">{{ $tab['count'] }}</span>
 @endif
 </button>
 @endif
 @endforeach
 </nav>

 @if (trim((string) $slot) !=='')
 <div class="mt-4">
 {{ $slot }}
 </div>
 @endif
</div>
