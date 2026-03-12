@props([
'icon'=>'🐾',
'title'=> null,
'description'=> null,
'message'=> null,
])

<div {{ $attributes->merge(['class'=>'flex w-full flex-col items-center justify-center px-4 py-16 text-center']) }}>
 @if(filled($icon))
 <div class="mb-4 text-5xl opacity-80">{{ $icon }}</div>
 @endif

 @if(filled($title))
 <h3 class="text-lg font-semibold font-display text-bark">{{ $title }}</h3>
 @endif

 @if(filled($description ?? $message))
 <p class="mx-auto mt-1 max-w-md text-sm text-fur">{{ $description ?? $message }}</p>
 @endif

 @if($slot->isNotEmpty())
 <div class="mt-3 max-w-md text-sm text-fur">
 {{ $slot }}
 </div>
 @endif

 @if(isset($action) || isset($actions))
 <div class="mt-6">
 {{ $action ?? $actions }}
 </div>
 @endif
</div>
