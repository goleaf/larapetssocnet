@props([
'group',
'owner'=> null,
'membership'=> null,
])

@php
 $groupRouteKey = filled((string) ($group->slug ??'')) ? $group->slug : $group->id;
 $privacyValue = strtolower((string) ($group->privacy ?? (($group->is_private ?? false) ?'private':'public')));
 $privacyLabel = \Illuminate\Support\Str::headline($privacyValue);
 $speciesValue = (string) data_get($group,'species','all_pets');
 $speciesLabel = \Illuminate\Support\Str::headline(str_replace(['-','_'],'', $speciesValue));

 $membershipStatus = strtolower((string) data_get($membership,'status',''));
 $isMember = $membership && in_array($membershipStatus, ['','active','accepted'], true);
 $isPending = $membership && $membershipStatus ==='pending';

 $coverUrl = (string) (data_get($group,'cover_photo_url') ?: data_get($group,'cover_image_path'));
 $avatarUrl = (string) (data_get($group,'avatar_url') ?: data_get($group,'profile_photo_url'));

 $typeClass = match ($privacyValue) {
'private'=>'bg-amber-50 text-amber-700 ring-amber-200',
'secret'=>'bg-rose-50 text-rose-700 ring-rose-200',
 default =>'bg-emerald-50 text-emerald-700 ring-emerald-200',
 };
@endphp

<article class="shell-card overflow-hidden p-0">
 <a href="{{ route('groups.show', $groupRouteKey) }}"class="block">
 <div class="h-24 w-full bg-[color:var(--ui-surface-muted)]">
 @if ($coverUrl !=='')
 <img src="{{ $coverUrl }}"alt="{{ $group->name }} cover"class="h-full w-full object-cover"loading="lazy">
 @else
 <div class="h-full w-full"style="background: linear-gradient(120deg, color-mix(in srgb, var(--ui-primary) 22%, var(--ui-surface) 78%), color-mix(in srgb, var(--ui-accent) 20%, var(--ui-surface) 80%));"></div>
 @endif
 </div>
 </a>

 <div class="space-y-3 p-4">
 <div class="flex items-start justify-between gap-3">
 <div class="min-w-0">
 <h3 class="truncate text-base font-semibold"style="color: var(--ui-text);">
 <a href="{{ route('groups.show', $groupRouteKey) }}"class="hover:underline">{{ $group->name }}</a>
 </h3>
 <p class="mt-0.5 text-xs shell-text-muted">{{ $speciesLabel }}</p>
 </div>

 <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $typeClass }}">
 {{ $privacyLabel }}
 </span>
 </div>

 @if (filled((string) $group->description))
 <p class="line-clamp-3 text-sm shell-text-muted">{{ \Illuminate\Support\Str::limit((string) $group->description, 150) }}</p>
 @endif

 <div class="flex items-center justify-between text-xs shell-text-muted">
 <span>{{ number_format((int) ($group->members_count ?? 0)) }} members</span>
 <span>{{ number_format((int) ($group->posts_count ?? 0)) }} posts</span>
 </div>

 <div class="flex items-center justify-between gap-2">
 @if ($owner)
 <a href="{{ route('profile.show', ['user'=> $owner]) }}"class="flex min-w-0 items-center gap-2">
 <x-avatar :src="$owner->avatar_url":name="$owner->name"size="xs"/>
 <span class="truncate text-xs shell-text-muted">{{ $owner->name }}</span>
 </a>
 @else
 <span class="text-xs shell-text-muted">Community group</span>
 @endif

 <div class="flex items-center gap-2">
 <x-ui.button :href="route('groups.show', $groupRouteKey)"variant="ghost"size="xs">View</x-ui.button>

 @auth
 @if ($isMember)
 <x-ui.badge variant="primary"size="sm"pill>Member</x-ui.badge>
 @elseif ($isPending)
 <x-ui.badge variant="warning"size="sm"pill>Pending</x-ui.badge>
 @elseif ($privacyValue !=='secret')
 <form method="POST"action="{{ route('groups.join', $groupRouteKey) }}"class="inline-block">
 @csrf
 <x-ui.button type="submit"variant="primary"size="xs">
 {{ $privacyValue ==='public'?'Join':'Request'}}
 </x-ui.button>
 </form>
 @endif
 @endauth
 </div>
 </div>
 </div>
</article>
