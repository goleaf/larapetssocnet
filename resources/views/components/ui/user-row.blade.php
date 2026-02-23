@props([
'name'=>'',
'avatar'=> null,
'src'=> null,
'subtitle'=> null,
'href'=> null,
])

@php
 $avatarSource = $src ?? $avatar;
@endphp

<div {{ $attributes->merge(['class'=>'flex items-center justify-between gap-3 rounded-lg p-3 transition-colors hover:bg-cream']) }}>
 <div class="min-w-0 flex items-center gap-3">
 @if($href)
 <a href="{{ $href }}"class="shrink-0">
 <x-ui.avatar :name="$name":src="$avatarSource"size="md"/>
 </a>

 <div class="min-w-0">
 <a href="{{ $href }}"class="block truncate text-sm font-semibold text-bark transition-colors hover:text-paw">{{ $name }}</a>
 @if(filled($subtitle))
 <p class="truncate text-xs text-fur">{{ $subtitle }}</p>
 @endif
 </div>
 @else
 <div class="shrink-0">
 <x-ui.avatar :name="$name":src="$avatarSource"size="md"/>
 </div>

 <div class="min-w-0">
 <p class="truncate text-sm font-semibold text-bark">{{ $name }}</p>
 @if(filled($subtitle))
 <p class="truncate text-xs text-fur">{{ $subtitle }}</p>
 @endif
 </div>
 @endif
 </div>

 @isset($action)
 <div class="flex shrink-0 items-center">
 {{ $action }}
 </div>
 @endisset
</div>
