@props([
'title'=> null,
'description'=> null,
'icon'=> null,
])

<section {{ $attributes->merge(['class'=>'flex flex-col gap-5 md:flex-row md:gap-6']) }}>
 @if ($title || $description || $icon)
 <div class="w-full shrink-0 md:w-1/3">
 <div class="flex items-start gap-3">
 @if ($icon)
 <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-pill bg-paw-light text-base"aria-hidden="true">
 {{ $icon }}
 </span>
 @endif

 <div class="space-y-1">
 @if ($title)
 <h3 class="text-base font-semibold font-display text-bark">{{ $title }}</h3>
 @endif

 @if ($description)
 <p class="text-sm text-fur">{{ $description }}</p>
 @endif
 </div>
 </div>
 </div>
 @endif

 <div class="w-full space-y-5 md:w-2/3 md:max-w-xl">
 {{ $slot }}
 </div>
</section>
