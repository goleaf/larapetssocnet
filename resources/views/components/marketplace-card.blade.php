@props([
'title'=>'Pet Carrier Backpack',
'price'=>'$45',
'condition'=>'Like new',
'location'=>'Local pickup',
'seller'=>'Community Seller',
'image'=> null,
'ctaLabel'=>'View Listing',
'ctaHref'=>'#',
])

<article {{ $attributes->merge(['class'=>'shell-card overflow-hidden']) }}>
 <div class="aspect-[16/10] w-full border-b"style="border-color: var(--ui-border); background: color-mix(in srgb, var(--ui-secondary) 14%, var(--ui-surface) 86%);">
 @if ($image)
 <img src="{{ $image }}"alt="{{ $title }}"class="h-full w-full object-cover"loading="lazy">
 @else
 <div class="flex h-full items-center justify-center text-4xl">🛍️</div>
 @endif
 </div>

 <div class="space-y-3 p-4">
 <div class="flex items-start justify-between gap-3">
 <h3 class="shell-title text-base">{{ $title }}</h3>
 <span class="chip">{{ $condition }}</span>
 </div>

 <p class="shell-title text-lg"style="color: var(--ui-primary);">{{ $price }}</p>

 <p class="text-sm shell-text-muted">📍 {{ $location }}</p>
 <p class="text-xs shell-text-muted">Seller: {{ $seller }}</p>

 <a href="{{ $ctaHref }}"class="btn-base btn-primary w-full justify-center text-sm">{{ $ctaLabel }}</a>
 </div>
</article>
