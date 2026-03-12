@props([
'src'=> null,
'name'=> null,
'alt'=> null,
'size'=>'md',
'status'=> null,
])

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
 @if (! $src)
 role="img"
 aria-label="{{ $alt ?? ($name ? $name.'avatar':'Avatar') }}"
 @endif
>
 @if ($src)
 <img src="{{ $src }}" alt="{{ $alt ?? ($name ? $name.'avatar':'Avatar') }}" class="h-full w-full object-cover" loading="lazy">
 @else
 <span class="font-heading font-bold" aria-hidden="true">{{ ($initials = collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('')) !== '' ? $initials : 'PA' }}</span>
 <span class="sr-only">{{ $alt ?? ($name ? $name.'avatar':'Avatar') }}</span>
 @endif

 @if (([
    'online' => 'bg-emerald-500',
    'away' => 'bg-amber-400',
    'busy' => 'bg-rose-500',
  ][$status] ?? null))
 <span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 {{ [
    'online' => 'bg-emerald-500',
    'away' => 'bg-amber-400',
    'busy' => 'bg-rose-500',
  ][$status] ?? null }}" style="border-color: var(--ui-surface);" aria-hidden="true"></span>
 @endif
</div>
