@props([
'type'=>'info',
'title'=> null,
'dismissible'=> false,
'icon'=> true,
'timeout'=> null,
])

<div
 {{ $attributes->merge(['class'=>'relative rounded-[var(--radius-soft)] p-4'.([
    'success' => 'border-l-4 border-leaf bg-leaf-light text-leaf',
    'error' => 'border-l-4 border-rose bg-rose-light text-rose',
    'warning' => 'border-l-4 border-amber bg-amber-light text-amber',
    'info' => 'border-l-4 border-sky bg-sky-light text-sky',
 ][match ((string) $type) {
    'danger' => 'error',
    'status' => 'info',
    default => (string) $type,
 }] ?? 'border-l-4 border-sky bg-sky-light text-sky')]) }}
 role="alert"
 @if($dismissible || (is_numeric($timeout) && max(0, (int) $timeout) !== null))
 x-data="{ open: true }"
 x-show="open"
 x-transition
 x-cloak
 @keydown.escape.window="open = false"
 @if(is_numeric($timeout))
 x-init="setTimeout(() => open = false, {{ max(0, (int) $timeout) }})"
 @endif
 @endif
>
 <div class="flex items-start gap-3">
	 @if($icon)
	 <div class="shrink-0">
	 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6">
	 {!! [
	'success' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
	'error' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
	'warning' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
	'info' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>',
	][match ((string) $type) {
	'danger' => 'error',
	'status' => 'info',
	default => (string) $type,
	}] ?? '<path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>' !!}
	 </svg>
	 </div>
	 @endif

 <div class="min-w-0 flex-1">
 @if(filled($title))
 <h3 class="mb-1 text-sm font-semibold">{{ $title }}</h3>
 @endif

 <div class="text-sm">
 {{ $slot }}
 </div>
 </div>

 @if($dismissible)
 <button
 type="button"
 class="-mr-1 -mt-1 shrink-0 p-1 opacity-70 transition-opacity hover:opacity-100"
 @click="open = false"
 >
 <span class="sr-only">Dismiss</span>
 <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
 <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22z"/>
 </svg>
 </button>
 @endif
 </div>
</div>
