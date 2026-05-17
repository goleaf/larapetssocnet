@props([
    'listing',
])

@php
    $listingId = (int) data_get($listing, 'id', 0);
    $title = (string) data_get($listing, 'title', 'Untitled listing');
    $description = (string) data_get($listing, 'description', 'No description yet.');
    $listingType = (string) data_get($listing, 'listing_type', 'listing');
    $status = (string) data_get($listing, 'status', 'draft');
    $isDeleted = filled(data_get($listing, 'deleted_at'));
    $location = (string) data_get($listing, 'location_text', 'Location not provided');
    $viewsCount = (int) data_get($listing, 'views_count', 0);
    $sellerName = (string) (data_get($listing, 'seller.name') ?: data_get($listing, 'user.name', 'Community seller'));
    $showHref = \Illuminate\Support\Facades\Route::has('marketplace.show') && $listingId > 0
        ? route('marketplace.show', $listingId)
        : null;

    $priceText = trim((string) data_get($listing, 'formatted_price', ''));

    if ($priceText === '') {
        $rawPrice = data_get($listing, 'price');
        $currency = strtoupper((string) data_get($listing, 'currency', 'USD'));
        $priceText = $rawPrice !== null && $rawPrice !== ''
            ? number_format((float) $rawPrice, 2) . ' ' . $currency
            : 'Price on request';
    }

    $imageUrl = trim((string) data_get($listing, 'cover_photo_url', ''));

    if ($imageUrl === '' && isset($listing) && is_object($listing) && method_exists($listing, 'getFirstMediaUrl')) {
        $imageUrl = (string) ($listing->getFirstMediaUrl('cover') ?: $listing->getFirstMediaUrl('gallery'));
    }

    $statusTone = match ($status) {
        'active' => 'success',
        'sold' => 'warning',
        'archived' => 'neutral',
        default => 'info',
    };

    if ($isDeleted) {
        $statusTone = 'danger';
    }
@endphp

<article
    class="shell-card group overflow-hidden transition-all duration-200 hover:-translate-y-0.5 hover:shadow-card-hover focus-within:shadow-card-hover md:grid md:grid-cols-[17rem_1fr]"
    data-ui="listing-card"
    aria-label="{{ __('Marketplace listing: :title', ['title' => $title]) }}"
>
    <div class="relative aspect-[16/10] w-full border-b bg-[color:var(--ui-surface-muted)] ui-border md:aspect-auto md:min-h-56 md:border-b-0 md:border-r">
        @if ($showHref)
            <a href="{{ $showHref }}" class="absolute inset-0 z-10 rounded-[var(--radius-card)] focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-paw" aria-label="{{ __('View listing: :title', ['title' => $title]) }}"></a>
        @endif

        @if ($imageUrl !== '')
            <img src="{{ $imageUrl }}" alt="{{ $title }}" class="h-full w-full object-cover" loading="lazy">
        @else
            <div class="flex h-full items-center justify-center text-4xl">🐶</div>
        @endif

        <div class="absolute left-3 top-3">
            <x-ui.badge :tone="$statusTone">
                {{ $isDeleted ? 'Deleted' : \Illuminate\Support\Str::headline($status) }}
            </x-ui.badge>
        </div>
    </div>

    <div class="flex min-w-0 flex-col gap-4 p-4 sm:p-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <h3 @class([
                'shell-title text-lg',
                'line-through opacity-70' => $isDeleted,
            ])>
                @if ($showHref)
                    <a href="{{ $showHref }}" class="focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw hover:text-paw">
                        {{ $title }}
                    </a>
                @else
                    {{ $title }}
                @endif
            </h3>

            <p class="shrink-0 text-right shell-title text-lg text-paw">{{ $priceText }}</p>
        </div>

        <p class="text-sm leading-6 shell-text-muted">{{ \Illuminate\Support\Str::limit($description, 150) }}</p>

        <div class="flex flex-wrap gap-2 text-xs shell-text-muted">
            <span class="chip min-h-8">{{ \Illuminate\Support\Str::headline($listingType) }}</span>
            <span class="inline-flex min-h-8 items-center rounded-[var(--radius-soft)] border border-whisker/40 px-2.5">📍 {{ $location }}</span>
            <span class="inline-flex min-h-8 items-center rounded-[var(--radius-soft)] border border-whisker/40 px-2.5">👁️ {{ number_format($viewsCount) }}</span>
            <span class="inline-flex min-h-8 items-center rounded-[var(--radius-soft)] border border-whisker/40 px-2.5">{{ $sellerName }}</span>
        </div>

        @if ($showHref)
            <div class="mt-auto flex justify-end">
                <x-ui.button :href="$showHref" variant="ghost" size="sm" class="min-h-11">
                    View details
                </x-ui.button>
            </div>
        @endif
    </div>
</article>
