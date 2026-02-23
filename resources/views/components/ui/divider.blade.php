@props([
'label'=> null,
])

@if(filled($label))
 <div {{ $attributes->merge(['class'=>'relative my-6 w-full']) }}>
 <div class="absolute inset-0 flex items-center"aria-hidden="true">
 <div class="w-full border-t border-whisker/40"></div>
 </div>

 <div class="relative flex justify-center">
 <span class="bg-cream px-3 text-sm text-fur">{{ $label }}</span>
 </div>
 </div>
@else
 <hr {{ $attributes->merge(['class'=>'my-6 border-t border-whisker/40']) }}>
@endif
