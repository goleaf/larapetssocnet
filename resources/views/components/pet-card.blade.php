@props([
'name'=>'Luna',
'species'=>'Dog',
'breed'=>'Mixed Breed',
'age'=>'2 years',
'location'=>'City Shelter',
'image'=> null,
'owner'=> null,
'ctaLabel'=>'View Profile',
'ctaHref'=>'#',
])

<article {{ $attributes->merge(['class'=>'shell-card overflow-hidden']) }}>
 <div class="aspect-[16/10] w-full overflow-hidden border-b" style="border-color: var(--ui-border); background: color-mix(in srgb, var(--ui-primary) 8%, var(--ui-surface) 92%);">
 @if ($image)
 <img src="{{ $image }}" alt="{{ $name }}" class="h-full w-full object-cover" loading="lazy">
 @else
 <div class="flex h-full items-center justify-center text-5xl">🐶</div>
 @endif
 </div>

 <div class="space-y-3 p-4">
 <div class="flex items-start justify-between gap-3">
 <div>
 <h3 class="shell-title text-lg">{{ $name }}</h3>
 <p class="text-sm shell-text-muted">{{ $species }} · {{ $breed }}</p>
 </div>
 <span class="chip">{{ $age }}</span>
 </div>

 <p class="text-sm shell-text-muted">📍 {{ $location }}</p>

 @if ($owner)
 <p class="text-xs shell-text-muted">Posted by {{ $owner }}</p>
 @endif

 <a href="{{ $ctaHref }}" class="btn-base btn-primary w-full justify-center text-sm">
 {{ $ctaLabel }}
 </a>
 </div>
</article>
