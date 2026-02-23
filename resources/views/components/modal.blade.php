@props([
'name',
'show'=> false,
'maxWidth'=>'2xl',
])

@php
 $maxWidthClass = [
'sm'=>'sm:max-w-sm',
'md'=>'sm:max-w-md',
'lg'=>'sm:max-w-lg',
'xl'=>'sm:max-w-xl',
'2xl'=>'sm:max-w-2xl',
 ][$maxWidth] ??'sm:max-w-2xl';

 $focusable = $attributes->has('focusable');
@endphp

<div
 {{ $attributes->except('focusable')->merge(['class'=>'relative z-50']) }}
 x-data="modalState(@js($show))"
 x-init="
 $watch('open', value => {
 document.body.classList.toggle('overflow-hidden', value);

 if (value && {{ $focusable ?'true':'false'}}) {
 $nextTick(() => {
 const focusableEl = $el.querySelector('a, button, input:not([type=hidden]), textarea, select, [tabindex]:not([tabindex=\'-1\'])');
 if (focusableEl) {
 focusableEl.focus();
 }
 });
 }
 });

 if (open) {
 document.body.classList.add('overflow-hidden');
 }
"
 x-on:open-modal.window="if ($event.detail ==='{{ $name }}') show()"
 x-on:close-modal.window="if ($event.detail ==='{{ $name }}') hide()"
 x-on:close.stop="hide()"
 x-on:keydown.escape.window="hide()"
>
 <div
 x-cloak
 x-show="open"
 class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0"
 style="display: none;"
 x-transition:enter="transition duration-200 ease-out"
 x-transition:enter-start="opacity-0"
 x-transition:enter-end="opacity-100"
 x-transition:leave="transition duration-150 ease-in"
 x-transition:leave-start="opacity-100"
 x-transition:leave-end="opacity-0"
 >
 <button
 type="button"
 class="absolute inset-0 h-full w-full"
 style="background: rgba(2, 6, 23, 0.6);"
 @click="hide()"
 aria-label="Close modal"
 ></button>

 <div class="relative mx-auto mb-6 w-full {{ $maxWidthClass }}">
 <div
 class="shell-card overflow-hidden"
 x-show="open"
 x-transition:enter="transition ease-out duration-200"
 x-transition:enter-start="opacity-0 translate-y-3 scale-95"
 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
 x-transition:leave="transition ease-in duration-150"
 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
 x-transition:leave-end="opacity-0 translate-y-3 scale-95"
 @click.stop
 >
 {{ $slot }}
 </div>
 </div>
 </div>
</div>
