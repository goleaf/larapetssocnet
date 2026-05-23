@props([
'users'=> [],
'max'=> 5,
'size'=>'md',
'total'=> null,
])

<div {{ $attributes->merge(['class'=>'flex items-center isolate']) }}>
 @foreach(collect(is_iterable($users) ? $users : [])->take(max(1, (int) $max))->values() as $index => $user)
 <x-ui.avatar
 :src="data_get($user, 'avatar_url')
    ?? data_get($user, 'avatar')
    ?? data_get($user, 'avatar_path')
    ?? data_get($user, 'src')
    ?? ((is_object($user) && method_exists($user, 'getAvatarUrl')) ? $user->getAvatarUrl() : null)
    ?? ((is_object($user) && method_exists($user, 'getFirstMediaUrl')) ? $user->getFirstMediaUrl('avatar') : null)"
 :name="(string) (data_get($user, 'name') ?? data_get($user, 'username') ?? 'User')"
 :size="$size"
 :user="$user"
 class="{{ $index > 0 ? ([
    'xs'=>'-ml-1.5',
    'sm'=>'-ml-2',
    'md'=>'-ml-3',
    'lg'=>'-ml-3.5',
    'xl'=>'-ml-4',
    '2xl'=>'-ml-5',
  ][(string) $size] ?? '-ml-3') : '' }} relative ring-2 ring-warm-white"
 style="z-index: {{ 20 - $index }}"
 />
 @endforeach

 @if(max(0, (($total !== null ? (int) $total : collect(is_iterable($users) ? $users : [])->count()) - collect(is_iterable($users) ? $users : [])->take(max(1, (int) $max))->values()->count())) > 0)
 <div
 class="{{ [
    'xs'=>'h-6 w-6 text-[0.625rem]',
    'sm'=>'h-8 w-8 text-xs',
    'md'=>'h-10 w-10 text-sm',
    'lg'=>'h-14 w-14 text-base',
    'xl'=>'h-20 w-20 text-xl',
    '2xl'=>'h-24 w-24 text-2xl',
  ][(string) $size] ?? 'h-10 w-10 text-sm' }} {{ collect(is_iterable($users) ? $users : [])->take(max(1, (int) $max))->isNotEmpty() ? ([
    'xs'=>'-ml-1.5',
    'sm'=>'-ml-2',
    'md'=>'-ml-3',
    'lg'=>'-ml-3.5',
    'xl'=>'-ml-4',
    '2xl'=>'-ml-5',
  ][(string) $size] ?? '-ml-3') : '' }} relative flex shrink-0 items-center justify-center rounded-pill border border-whisker/30 bg-cream font-medium text-fur ring-2 ring-warm-white"
 style="z-index: 0"
 aria-label="{{ max(0, (($total !== null ? (int) $total : collect(is_iterable($users) ? $users : [])->count()) - collect(is_iterable($users) ? $users : [])->take(max(1, (int) $max))->values()->count())) }} more"
 title="{{ max(0, (($total !== null ? (int) $total : collect(is_iterable($users) ? $users : [])->count()) - collect(is_iterable($users) ? $users : [])->take(max(1, (int) $max))->values()->count())) }} more"
 >
 +{{ max(0, (($total !== null ? (int) $total : collect(is_iterable($users) ? $users : [])->count()) - collect(is_iterable($users) ? $users : [])->take(max(1, (int) $max))->values()->count())) }}
 </div>
 @endif
</div>
