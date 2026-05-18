@props([
'id'=> null,
'name'=> null,
'title'=> null,
'description'=> null,
'size'=>'md',
'trigger'=> true,
'open'=> false,
])

@php
 $modalId = $name ?: ($id ?: \Illuminate\Support\Str::random(8));
 $triggerSlot = $trigger ?? $triggerSlot ?? null;
 $externalShowExpression = $attributes->get('x-show');

 $maxWidths = [
'sm'=>'sm:max-w-sm',
'md'=>'sm:max-w-md',
'lg'=>'sm:max-w-lg',
'xl'=>'sm:max-w-2xl',
'2xl'=>'sm:max-w-4xl',
 ];

 $maxWidthClass = $maxWidths[(string) $size] ?? $maxWidths['md'];
@endphp

<div
 {{ $attributes->except(['class','x-show'])->merge(['class'=>'contents']) }}
 x-data="{
 open: {{ $open ?'true':'false'}},
 show() {
 this.open = true;
 document.body.classList.add('overflow-hidden');
 },
 hide() {
 this.open = false;
 document.body.classList.remove('overflow-hidden');
 this.$dispatch('close');
 },
 toggle() {
 this.open ? this.hide() : this.show();
 },
 }"
 @open-modal.window="if ($event.detail === @js($modalId)) { show() }"
 @close-modal.window="if ($event.detail === @js($modalId)) { hide() }"
 @keydown.escape.window="if (open) { hide() }"
 @if($externalShowExpression)
 x-effect="open = Boolean({{ $externalShowExpression }})"
 @endif
>
 @if($trigger && $triggerSlot)
 <div @click="show()" class="inline-block">
 {{ $triggerSlot }}
 </div>
 @endif

 <div
 x-show="open"
 x-cloak
 style="display: none;"
 class="fixed inset-0 z-50 overflow-y-auto"
 role="dialog"
 aria-modal="true"
 aria-labelledby="modal-title-{{ $modalId }}"
 >
 <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
 <div
 x-show="open"
 x-transition:enter="ease-out duration-300"
 x-transition:enter-start="opacity-0"
 x-transition:enter-end="opacity-100"
 x-transition:leave="ease-in duration-200"
 x-transition:leave-start="opacity-100"
 x-transition:leave-end="opacity-0"
 class="fixed inset-0 bg-bark/40"
 @click="hide()"
 aria-hidden="true"
 ></div>

 <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

 <div
 x-show="open"
 x-transition:enter="ease-out duration-300 transform"
 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
 x-transition:leave="ease-in duration-200 transform"
 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
 class="inline-block w-full transform overflow-hidden rounded-[var(--radius-card)] bg-[color:var(--surface-modal)] text-left align-bottom transition-all sm:my-8 sm:align-middle {{ $maxWidthClass }}"
 @click.stop
 >
 @isset($header)
 {{ $header }}
 @elseif($title)
 <div class="flex items-start justify-between border-b border-whisker/40 px-6 py-4">
 <div>
 <h3 class="text-lg font-semibold font-display text-bark" id="modal-title-{{ $modalId }}">{{ $title }}</h3>
 @if(filled($description))
 <p class="mt-1 text-sm text-fur">{{ $description }}</p>
 @endif
 </div>

 <button
 type="button"
 class="text-whisker transition-colors hover:text-bark focus:outline-none focus:ring-2 focus:ring-paw"
 @click="hide()"
 >
 <span class="sr-only">Close</span>
 <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
 </svg>
 </button>
 </div>
 @endif

 <div class="px-6 py-5">
 @isset($body)
 {{ $body }}
 @else
 {{ $slot }}
 @endisset
 </div>

 @isset($footer)
 <div class="flex items-center justify-end gap-3 border-t border-whisker/40 bg-cream/50 px-6 py-4">
 {{ $footer }}
 </div>
 @endisset
 </div>
 </div>
 </div>
</div>
