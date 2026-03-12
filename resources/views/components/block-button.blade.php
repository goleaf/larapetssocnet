@props([
'blocked'=> false,
'busy'=> false,
'size'=>'md',
])

<button
 type="button"
 {{ $attributes->merge(['class'=>"btn-base btn-ghost ".match ($size) {
 'sm' => 'px-3 py-2 text-xs',
 'lg' => 'px-5 py-3 text-sm',
 default => 'px-4 py-2.5 text-sm',
 }]) }}
 @if (! $attributes->has(':disabled') && ! $attributes->has('x-bind:disabled'))
 @disabled($busy)
 @endif
 @if (! $attributes->has(':aria-pressed') && ! $attributes->has('x-bind:aria-pressed'))
 aria-pressed="{{ $blocked ?'true':'false'}}"
 @endif
>
 {{ $slot }}
</button>
