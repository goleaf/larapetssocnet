@props([
'src'=> null,
'name'=> null,
'alt'=> null,
'size'=>'md',
'status'=> null,
'user'=> null,
])

@php
 $avatarUser = $user instanceof \App\Models\Identity\User ? $user : null;
 $avatarName = $name ?? $avatarUser?->name;
 $avatarSrc = $src ?? $avatarUser?->avatar_url;
 $avatarStatus = $avatarUser instanceof \App\Models\Identity\User
     ? ($avatarUser->shouldShowActiveStatus() ? 'online' : null)
     : $status;
 $avatarLabel = $alt ?? ($avatarName ? $avatarName.' avatar' : 'Avatar');
@endphp

<div
 {{ $attributes->merge(['class'=>"relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full border-2 ".([
    'xs' => 'h-7 w-7 text-[0.62rem]',
    'sm' => 'h-8 w-8 text-xs',
    'md' => 'h-10 w-10 text-sm',
    'lg' => 'h-12 w-12 text-base',
    'xl' => 'h-14 w-14 text-lg',
    '2xl' => 'h-16 w-16 text-xl',
  ][$size] ?? 'h-10 w-10 text-sm')]) }}
 style="background: color-mix(in srgb, var(--ui-primary) 14%, var(--ui-surface) 86%); color: var(--ui-primary); border-color: color-mix(in srgb, var(--ui-primary) 26%, var(--ui-border) 74%);"
 @if (! $avatarSrc)
 role="img"
 aria-label="{{ $avatarLabel }}"
 @endif
>
 @if ($avatarSrc)
 <img src="{{ $avatarSrc }}" alt="{{ $avatarLabel }}" class="h-full w-full object-cover" loading="lazy">
 @else
 <span class="font-heading font-bold" aria-hidden="true">{{ ($initials = collect(preg_split('/\s+/', trim((string) $avatarName)))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('')) !== '' ? $initials : 'PA' }}</span>
 <span class="sr-only">{{ $avatarLabel }}</span>
 @endif

 @if (([
    'online' => 'bg-emerald-500',
    'away' => 'bg-amber-400',
    'busy' => 'bg-rose-500',
  ][$avatarStatus] ?? null))
 <span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 {{ [
    'online' => 'bg-emerald-500',
    'away' => 'bg-amber-400',
    'busy' => 'bg-rose-500',
  ][$avatarStatus] ?? null }}" style="border-color: var(--ui-surface);" @if ($avatarStatus === 'online') data-ui="active-status-indicator" title="Currently active" @endif aria-hidden="true"></span>
 @if ($avatarStatus === 'online')
 <span class="sr-only">Currently active</span>
 @endif
 @endif
</div>
