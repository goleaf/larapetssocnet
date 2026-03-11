<div
 class="pointer-events-none fixed bottom-4 right-4 z-50 flex w-full max-w-sm flex-col gap-2 px-4 sm:px-0"
 x-data
 x-cloak
 aria-live="polite"
 aria-atomic="true"
>
 <template x-for="item in (($store.toast && $store.toast.items) ? $store.toast.items : [])" :key="item.id">
 <div
 class="pointer-events-auto flex w-full overflow-hidden rounded-lg border bg-warm-white shadow-card-hover"
 :class="{
'border-leaf-light': item.type ==='success',
'border-rose-light': item.type ==='error',
'border-amber-light': item.type ==='warning',
'border-sky-light': item.type ==='info',
 }"
 x-transition:enter="transition transform ease-out duration-300"
 x-transition:enter-start="translate-x-full opacity-0"
 x-transition:enter-end="translate-x-0 opacity-100"
 x-transition:leave="transition transform ease-in duration-200"
 x-transition:leave-start="translate-x-0 opacity-100"
 x-transition:leave-end="translate-x-full opacity-0"
 >
 <div
 class="w-1.5 shrink-0"
 :class="{
'bg-leaf': item.type ==='success',
'bg-rose': item.type ==='error',
'bg-amber': item.type ==='warning',
'bg-sky': item.type ==='info',
 }"
 ></div>

 <div class="flex w-full items-start gap-3 px-3 py-3">
 <div class="mt-0.5 shrink-0">
 <template x-if="item.type ==='success'">
 <svg class="h-5 w-5 text-leaf" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
 </svg>
 </template>

 <template x-if="item.type ==='error'">
 <svg class="h-5 w-5 text-rose" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
 </svg>
 </template>

 <template x-if="item.type ==='warning'">
 <svg class="h-5 w-5 text-amber" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
 </svg>
 </template>

 <template x-if="item.type ==='info'">
 <svg class="h-5 w-5 text-sky" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
 </svg>
 </template>
 </div>

 <div class="flex-1 pr-2 text-sm font-medium text-bark" x-text="item.message"></div>

 <button
 type="button"
 class="-mr-1 -mt-1 shrink-0 rounded-md p-1 text-whisker transition-colors hover:text-bark focus:outline-none focus:ring-2 focus:ring-paw focus:ring-offset-1"
 @click="if ($store.toast) { $store.toast.remove(item.id) }"
 >
 <span class="sr-only">Dismiss</span>
 <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
 <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22z" />
 </svg>
 </button>
 </div>
 </div>
 </template>
</div>
