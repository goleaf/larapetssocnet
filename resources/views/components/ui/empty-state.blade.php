@props([
'icon'=>'🐾',
'title'=> null,
'description'=> null,
'message'=> null,
])

@php
 $resolvedDescription = $description ?? $message;
 $actionSlot = $action ?? $actions ?? null;
@endphp

<div {{ $attributes->merge(['class'=>'flex w-full flex-col items-center justify-center px-4 py-16 text-center']) }}>
 @if(filled($icon))
 <div class="mb-4 text-5xl opacity-80">{{ $icon }}</div>
 @endif

 @if(filled($title))
 <h3 class="text-lg font-semibold font-display text-bark">{{ $title }}</h3>
 @endif

 @if(filled($resolvedDescription))
 <p class="mx-auto mt-1 max-w-md text-sm text-fur">{{ $resolvedDescription }}</p>
 @endif

 @if($slot->isNotEmpty())
 <div class="mt-3 max-w-md text-sm text-fur">
 {{ $slot }}
 </div>
 @endif

 @if($actionSlot)
 <div class="mt-6">
 {{ $actionSlot }}
 </div>
 @endif
</div>
