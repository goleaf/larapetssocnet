@props([
'title'=> null,
'subtitle'=> null,
'description'=> null,
'tight'=> false,
])

<section {{ $attributes->merge(['class'=> $tight ?'mb-4':'mb-8']) }}>
 @if(filled($title) || filled($description ?? $subtitle) || ($action ?? $actions ?? null))
 <div class="flex flex-col justify-between gap-4 border-b border-whisker/40 pb-4 sm:flex-row sm:items-end">
 <div>
 @if(filled($title))
 <h2 class="text-2xl font-bold font-display text-bark">{{ $title }}</h2>
 @endif

 @if(filled($description ?? $subtitle))
 <p class="mt-1 text-sm text-fur">{{ $description ?? $subtitle }}</p>
 @endif
 </div>

 @if($action ?? $actions ?? null)
 <div class="shrink-0 sm:mb-1">
 {{ $action ?? $actions ?? null }}
 </div>
 @endif
 </div>
 @endif

 <div @class(['pt-6'=> ! $tight,'pt-4'=> $tight])>
 {{ $slot }}
 </div>
</section>
