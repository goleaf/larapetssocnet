@props([
'src'=> null,
'name'=>'User',
'alt'=> null,
'size'=>'md',
'online'=> false,
'user'=> null,
])

@php
 $avatarUser = $user instanceof \App\Models\Identity\User ? $user : null;
 $avatarName = $avatarUser?->name ?? $name;
 $avatarSrc = $src ?? $avatarUser?->avatar_url;
 $avatarLabel = filled(trim((string) $alt))
     ? trim((string) $alt)
     : (trim((string) $avatarName) !== '' ? (string) $avatarName : 'User');
 $fallbackClasses = $avatarUser?->profile_default_avatar_color ?? [
    'bg-paw-light text-paw-dark',
    'bg-leaf-light text-leaf',
    'bg-sky-light text-sky',
    'bg-amber-light text-amber',
    'bg-rose-light text-rose',
 ][abs(crc32(trim((string) $avatarName) !== '' ? (string) $avatarName : 'User')) % 5];
 $fallbackInitial = $avatarUser?->profile_initial
     ?? (($initials = collect(preg_split('/\s+/', trim((string) $avatarName) !== '' ? (string) $avatarName : 'User', -1, PREG_SPLIT_NO_EMPTY) ?: [])->map(static fn (string $segment): string => mb_substr($segment, 0, 1))->take(2)->join('')) !== '' ? $initials : 'U');
 $showsActiveStatus = $avatarUser instanceof \App\Models\Identity\User
     ? $avatarUser->shouldShowActiveStatus()
     : (bool) $online;
@endphp

<div {{ $attributes->merge(['class'=>'relative inline-block shrink-0']) }}>
 @if(filled(is_string($avatarSrc) ? trim($avatarSrc) : null))
 <img
 src="{{ is_string($avatarSrc) ? trim($avatarSrc) : null }}"
 alt="{{ $avatarLabel }}"
 loading="lazy"
 class="{{ [
    'xs'=>'h-6 w-6 text-[0.625rem]',
    'sm'=>'h-8 w-8 text-xs',
    'md'=>'h-10 w-10 text-sm',
    'profile-list'=>'h-12 w-12 text-base',
    'lg'=>'h-14 w-14 text-base',
    'xl'=>'h-20 w-20 text-xl',
    '2xl'=>'h-24 w-24 text-2xl',
  ][(string) $size] ?? 'h-10 w-10 text-sm' }} rounded-pill border border-whisker/30 bg-cream object-cover"
 >
 @else
 <div class="{{ [
    'xs'=>'h-6 w-6 text-[0.625rem]',
    'sm'=>'h-8 w-8 text-xs',
    'md'=>'h-10 w-10 text-sm',
    'profile-list'=>'h-12 w-12 text-base',
    'lg'=>'h-14 w-14 text-base',
    'xl'=>'h-20 w-20 text-xl',
    '2xl'=>'h-24 w-24 text-2xl',
  ][(string) $size] ?? 'h-10 w-10 text-sm' }} {{ $fallbackClasses }} flex items-center justify-center rounded-pill border border-whisker/30 font-bold font-display uppercase" role="img" aria-label="{{ $avatarLabel }}">
 {{ $fallbackInitial }}
 </div>
 @endif

 @if($showsActiveStatus)
 <span data-ui="active-status-indicator" title="Currently active" aria-hidden="true" class="absolute block rounded-pill border-warm-white bg-leaf {{ [
    'xs'=>'h-1.5 w-1.5 border-[1.5px] bottom-0 right-0',
    'sm'=>'h-2.5 w-2.5 border-2 bottom-0 right-0',
    'md'=>'h-3 w-3 border-2 bottom-0 right-0',
    'profile-list'=>'h-3.5 w-3.5 border-2 bottom-0.5 right-0.5',
    'lg'=>'h-4 w-4 border-2 bottom-0.5 right-0.5',
    'xl'=>'h-5 w-5 border-[3px] bottom-1 right-1',
    '2xl'=>'h-6 w-6 border-[3px] bottom-1 right-1',
  ][(string) $size] ?? 'h-3 w-3 border-2 bottom-0 right-0' }}"></span>
 <span class="sr-only">Currently active</span>
 @endif
</div>
