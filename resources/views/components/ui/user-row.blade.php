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
 $verifiedTooltipId = $rowUser ? 'user-row-verified-'.$rowUser->getKey() : 'user-row-verified-'.md5($rowName);
@endphp

<div {{ $attributes->merge(['class'=>'flex items-center justify-between gap-3 rounded-[var(--radius-soft)] p-3 transition-colors hover:bg-cream']) }}>
 <div class="min-w-0 flex items-center gap-3">
 @if($href)
 <a href="{{ $href }}" class="shrink-0">
 <x-ui.avatar :name="$rowName" :src="$rowAvatar" :user="$rowUser" size="md"/>
 </a>

 <div class="min-w-0">
 <div class="flex min-w-0 items-center gap-1.5">
 <a href="{{ $href }}" class="block min-w-0 truncate text-sm font-semibold text-bark transition-colors hover:text-paw">{{ $rowName }}</a>
 @if ($rowUser?->profile_verified)
 <x-ui.verified-badge :tooltip-id="$verifiedTooltipId" size="sm"/>
 @endif
 </div>
 @if(filled($subtitle))
 <p class="truncate text-xs text-fur">{{ $subtitle }}</p>
 @endif
 </div>
 @else
 <div class="shrink-0">
 <x-ui.avatar :name="$rowName" :src="$rowAvatar" :user="$rowUser" size="md"/>
 </div>

 <div class="min-w-0">
 <div class="flex min-w-0 items-center gap-1.5">
 <p class="min-w-0 truncate text-sm font-semibold text-bark">{{ $rowName }}</p>
 @if ($rowUser?->profile_verified)
 <x-ui.verified-badge :tooltip-id="$verifiedTooltipId" size="sm"/>
 @endif
 </div>
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
