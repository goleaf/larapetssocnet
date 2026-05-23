@props([
'name'=>'',
'avatar'=> null,
'src'=> null,
'subtitle'=> null,
'href'=> null,
'user'=> null,
])

@php
 $rowUser = $user instanceof \App\Models\Identity\User ? $user : null;
 $rowName = filled($name) ? (string) $name : (string) ($rowUser?->display_name ?: $rowUser?->name ?: 'User');
 $rowAvatar = $src ?? $avatar ?? $rowUser?->avatar_url;
@endphp

<div {{ $attributes->merge(['class'=>'flex items-center justify-between gap-3 rounded-[var(--radius-soft)] p-3 transition-colors hover:bg-cream']) }}>
 <div class="min-w-0 flex items-center gap-3">
 @if($href)
 <a href="{{ $href }}" class="shrink-0">
 <x-ui.avatar :name="$rowName" :src="$rowAvatar" :user="$rowUser" size="md"/>
 </a>

 <div class="min-w-0">
 <a href="{{ $href }}" class="block truncate text-sm font-semibold text-bark transition-colors hover:text-paw">{{ $rowName }}</a>
 @if(filled($subtitle))
 <p class="truncate text-xs text-fur">{{ $subtitle }}</p>
 @endif
 </div>
 @else
 <div class="shrink-0">
 <x-ui.avatar :name="$rowName" :src="$rowAvatar" :user="$rowUser" size="md"/>
 </div>

 <div class="min-w-0">
 <p class="truncate text-sm font-semibold text-bark">{{ $rowName }}</p>
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
