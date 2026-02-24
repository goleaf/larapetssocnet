@props([
'type'=>'info',
'message'=> null,
'title'=> null,
'dismissible'=> true,
'timeout'=> 5000,
])

@php
 $styles = [
'success'=> [
'icon'=>'✓',
'container'=>'border-emerald-300/80 bg-emerald-500/10',
'titleStyle'=>'color: color-mix(in srgb, var(--ui-primary) 70%, var(--ui-text) 30%);',
 ],
'error'=> [
'icon'=>'!',
'container'=>'border-rose-300/80 bg-rose-500/10',
'titleStyle'=>'color: color-mix(in srgb, var(--ui-danger) 72%, var(--ui-text) 28%);',
 ],
'warning'=> [
'icon'=>'!',
'container'=>'border-amber-300/80 bg-amber-500/10',
'titleStyle'=>'color: color-mix(in srgb, var(--ui-secondary) 70%, var(--ui-text) 30%);',
 ],
'info'=> [
'icon'=>'i',
'container'=>'border-sky-300/80 bg-sky-500/10',
'titleStyle'=>'color: color-mix(in srgb, var(--ui-accent) 70%, var(--ui-text) 30%);',
 ],
 ][$type] ?? [
'icon'=>'i',
'container'=>'border-sky-300/80 bg-sky-500/10',
'titleStyle'=>'color: color-mix(in srgb, var(--ui-accent) 70%, var(--ui-text) 30%);',
 ];

 $content = $message ?? trim((string) $slot);
@endphp

@if (filled($content))
 <div
 x-data="flashMessage({{ (int) $timeout }})"
 x-cloak
 x-show="visible"
 x-transition:enter="transition duration-200 ease-out"
 x-transition:enter-start="opacity-0 translate-y-2"
 x-transition:enter-end="opacity-100 translate-y-0"
 x-transition:leave="transition duration-150 ease-in"
 x-transition:leave-start="opacity-100"
 x-transition:leave-end="opacity-0 translate-y-1"
 {{ $attributes->merge(['class'=>"rounded-xl border px-3.5 py-3 shadow-soft {$styles['container']}"]) }}
 role="status"
 aria-live="polite"
 >
 <div class="flex items-start gap-3">
 <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs font-bold"style="border-color: currentColor;">
 {{ $styles['icon'] }}
 </span>

 <div class="min-w-0 flex-1">
 @if ($title)
 <p class="text-sm font-semibold"style="{{ $styles['titleStyle'] }}">{{ $title }}</p>
 @endif
 <p class="text-sm leading-5"style="color: var(--ui-text);">{{ $content }}</p>
 </div>

 @if ($dismissible)
 <button type="button" class="icon-button h-7 w-7"@click="close()"aria-label="Dismiss message">
 <svg class="h-4 w-4"viewBox="0 0 20 20"fill="none"stroke="currentColor"stroke-width="1.8">
 <path d="M5 5l10 10M15 5L5 15"stroke-linecap="round"/>
 </svg>
 </button>
 @endif
 </div>
 </div>
@endif
