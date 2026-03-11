@php
 $title = (string) data_get($listing,'title','Untitled listing');
 $description = (string) data_get($listing,'description','No description yet.');
 $listingType = (string) data_get($listing,'listing_type','listing');
 $status = (string) data_get($listing,'status','draft');
 $isDeleted = filled(data_get($listing,'deleted_at'));
 $location = (string) data_get($listing,'location_text','Location not provided');
 $viewsCount = (int) data_get($listing,'views_count', 0);

 $priceText = trim((string) data_get($listing,'formatted_price',''));

 if ($priceText ==='') {
 $rawPrice = data_get($listing,'price');
 $currency = strtoupper((string) data_get($listing,'currency','USD'));
 $priceText = $rawPrice !== null && $rawPrice !==''
 ? number_format((float) $rawPrice, 2).''.$currency
 :'Price on request';
 }

 $imageUrl = trim((string) data_get($listing,'cover_photo_url',''));

 if ($imageUrl ===''&& isset($listing) && is_object($listing) && method_exists($listing,'getFirstMediaUrl')) {
 $imageUrl = (string) ($listing->getFirstMediaUrl('cover') ?: $listing->getFirstMediaUrl('gallery'));
 }

 $statusTone = match ($status) {
'active'=>'success',
'sold'=>'warning',
'archived'=>'neutral',
 default =>'info',
 };

 if ($isDeleted) {
 $statusTone ='danger';
 }
@endphp

<article class="shell-card overflow-hidden">
 <div class="aspect-[16/10] w-full border-b" style="border-color: var(--ui-border); background: color-mix(in srgb, var(--ui-secondary) 12%, var(--ui-surface) 88%);">
 @if ($imageUrl !=='')
 <img src="{{ $imageUrl }}" alt="{{ $title }}" class="h-full w-full object-cover" loading="lazy">
 @else
 <div class="flex h-full items-center justify-center text-4xl">🐶</div>
 @endif
 </div>

 <div class="space-y-3 p-4">
 <div class="flex items-start justify-between gap-2">
 <h3 @class([
'shell-title text-base',
'line-through opacity-70'=> $isDeleted,
 ])>{{ $title }}</h3>

 <x-ui.badge :tone="$statusTone">
 {{ $isDeleted ?'Deleted': \Illuminate\Support\Str::headline($status) }}
 </x-ui.badge>
 </div>

 <p class="shell-title text-lg" style="color: var(--ui-primary);">{{ $priceText }}</p>

 <p class="text-sm shell-text-muted">{{ \Illuminate\Support\Str::limit($description, 90) }}</p>

 <div class="flex flex-wrap gap-2 text-xs shell-text-muted">
 <span class="chip">{{ \Illuminate\Support\Str::headline($listingType) }}</span>
 <span>📍 {{ $location }}</span>
 <span>👁️ {{ number_format($viewsCount) }}</span>
 </div>
 </div>
</article>
