@props([
'users'=> [],
'max'=> 5,
'size'=>'md',
'total'=> null,
])

@php
 $items = collect(is_iterable($users) ? $users : []);
 $maxItems = max(1, (int) $max);
 $displayUsers = $items->take($maxItems)->values();
 $actualTotal = $total !== null ? (int) $total : $items->count();
 $remaining = max(0, $actualTotal - $displayUsers->count());

 $margins = [
'xs'=>'-ml-1.5',
'sm'=>'-ml-2',
'md'=>'-ml-3',
'lg'=>'-ml-3.5',
'xl'=>'-ml-4',
'2xl'=>'-ml-5',
 ];

 $marginClass = $margins[(string) $size] ?? $margins['md'];

 $fallbackSizeClasses = [
'xs'=>'h-6 w-6 text-[0.625rem]',
'sm'=>'h-8 w-8 text-xs',
'md'=>'h-10 w-10 text-sm',
'lg'=>'h-14 w-14 text-base',
'xl'=>'h-20 w-20 text-xl',
'2xl'=>'h-24 w-24 text-2xl',
 ];

 $fallbackSizeClass = $fallbackSizeClasses[(string) $size] ?? $fallbackSizeClasses['md'];
@endphp

<div {{ $attributes->merge(['class'=>'flex items-center isolate']) }}>
 @foreach($displayUsers as $index => $user)
 @php
 $name = (string) (data_get($user,'name') ?? data_get($user,'username') ??'User');

 $src = data_get($user,'avatar_url')
 ?? data_get($user,'avatar')
 ?? data_get($user,'avatar_path')
 ?? data_get($user,'src');

 if (! $src && is_object($user) && method_exists($user,'getAvatarUrl')) {
 $src = $user->getAvatarUrl();
 }

 if (! $src && is_object($user) && method_exists($user,'getFirstMediaUrl')) {
 $src = $user->getFirstMediaUrl('avatar');
 }
 @endphp

 <x-ui.avatar
 :src="$src"
 :name="$name"
 :size="$size"
 class="{{ $index > 0 ? $marginClass :''}} relative ring-2 ring-warm-white"
 style="z-index: {{ 20 - $index }}"
 />
 @endforeach

 @if($remaining > 0)
 <div
 class="{{ $fallbackSizeClass }} {{ $displayUsers->isNotEmpty() ? $marginClass :''}} relative flex shrink-0 items-center justify-center rounded-pill border border-whisker/30 bg-cream font-medium text-fur ring-2 ring-warm-white"
 style="z-index: 0"
 aria-label="{{ $remaining }} more"
 title="{{ $remaining }} more"
 >
 +{{ $remaining }}
 </div>
 @endif
</div>
