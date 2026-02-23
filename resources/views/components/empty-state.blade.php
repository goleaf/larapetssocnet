@props([
'icon'=>'🐾',
'title'=>'Nothing here yet',
'description'=>'When new activity appears, this section will fill up.',
'actionLabel'=> null,
'actionHref'=>'#',
])

<div {{ $attributes->merge(['class'=>'shell-card p-8 text-center']) }}>
 <div class="mx-auto mb-4 inline-flex h-14 w-14 items-center justify-center rounded-2xl text-2xl"style="background: color-mix(in srgb, var(--ui-primary) 16%, var(--ui-surface) 84%);">
 {{ $icon }}
 </div>

 <h3 class="shell-title text-xl">{{ $title }}</h3>
 <p class="mx-auto mt-2 max-w-md text-sm shell-text-muted">{{ $description }}</p>

 @if ($actionLabel)
 <a href="{{ $actionHref }}"class="btn-base btn-primary mt-5">
 {{ $actionLabel }}
 </a>
 @endif
</div>
