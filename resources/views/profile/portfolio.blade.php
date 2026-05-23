@php
 $displayName = $profileUser->display_name ?: $profileUser->name;
 $portfolioDescription = $profileUser->headline ?: ($profileUser->bio ?: 'A curated PetSocial portfolio.');
 $portfolioCount = $portfolioPosts->count();
 $canOpenPosts = auth()->check() && \Illuminate\Support\Facades\Route::has('posts.show');
 $slotClasses = static function (int $index): string {
 return match ($index) {
 0 => 'lg:col-span-3 lg:row-span-2',
 1 => 'lg:col-span-3',
 2, 3 => 'lg:col-span-2',
 4, 5 => 'lg:col-span-1',
 default => 'lg:col-span-2 xl:col-span-1',
 };
 };
@endphp

@section('title', $displayName.' Portfolio')

@push('meta')
 <meta property="og:type" content="profile">
 <meta property="og:title" content="{{ $displayName }} Portfolio on PetSocial">
 <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $portfolioDescription), 150) }}">
 <meta property="og:url" content="{{ $portfolioUrl }}">
 <meta name="twitter:card" content="summary_large_image">
 <meta name="twitter:title" content="{{ $displayName }} Portfolio on PetSocial">
 <meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $portfolioDescription), 150) }}">
 <link rel="canonical" href="{{ $portfolioUrl }}">
@endpush

<x-app-layout>
 <div class="space-y-5" data-ui="profile-portfolio-page">
 <section class="relative overflow-hidden rounded-[var(--radius-card)] border border-whisker/40 {{ $profileUser->profile_default_gradient }} px-5 py-8 shadow-card sm:px-7 lg:px-8" data-ui="profile-portfolio-hero">
 <div class="absolute inset-0 bg-warm-white/55" aria-hidden="true"></div>
 <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
 <div class="min-w-0">
 <p class="chip min-h-8">Portfolio</p>
 <div class="mt-4 flex flex-wrap items-center gap-3">
 <h1 class="font-display text-3xl font-bold text-bark sm:text-4xl">{{ $displayName }}</h1>
 @if ($profileUser->profile_verified)
 <x-ui.verified-badge tooltip-id="portfolio-verified-tooltip"/>
 @endif
 </div>
 <p class="mt-1 text-base font-semibold text-fur">{{ '@'.$profileUser->username }}</p>
 <p class="mt-4 max-w-2xl text-sm leading-6 text-bark">{{ \Illuminate\Support\Str::limit(strip_tags((string) $portfolioDescription), 190) }}</p>
 <div class="mt-5 flex flex-wrap gap-2 text-xs font-semibold text-fur">
 <span class="rounded-full border border-whisker/40 bg-warm-white/75 px-3 py-1">{{ number_format($portfolioCount) }} featured {{ \Illuminate\Support\Str::plural('post', $portfolioCount) }}</span>
 <span class="rounded-full border border-whisker/40 bg-warm-white/75 px-3 py-1">Public showcase</span>
 </div>
 </div>

 <div class="flex flex-col gap-2 sm:flex-row lg:justify-end">
 <x-ui.button :href="route('profile.show', ['user' => $profileUser->username])" variant="default" class="min-h-11">
 Standard Profile
 </x-ui.button>
 <x-ui.button :href="$portfolioUrl" variant="primary" class="min-h-11">
 Portfolio Link
 </x-ui.button>
 </div>
 </div>
 </section>

 @if ($portfolioPosts->isEmpty())
 <section class="rounded-[var(--radius-card)] border border-dashed border-whisker/60 bg-warm-white shadow-card" data-ui="profile-portfolio-empty">
 <x-ui.empty-state icon="" title="No portfolio posts yet" description="This portfolio will show selected public posts once the profile owner curates their showcase."/>
 </section>
 @else
 <section aria-labelledby="portfolio-grid-title" class="space-y-4" data-ui="profile-portfolio-grid-section">
 <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
 <div>
 <p class="text-xs font-bold uppercase text-paw">Curated highlights</p>
 <h2 id="portfolio-grid-title" class="font-display text-2xl font-bold text-bark">Best public posts</h2>
 </div>
 <p class="text-sm text-fur">Arranged as a fixed magazine grid for sharing outside PetSocial.</p>
 </div>

 <div class="grid auto-rows-[18rem] grid-cols-1 gap-3 sm:grid-cols-2 lg:auto-rows-[13rem] lg:grid-cols-6" role="list" aria-label="{{ $displayName }} portfolio posts">
 @foreach ($portfolioPosts as $post)
 @php
 $mediaItem = $post->mediaItemsForDisplay()->first();
 $mediaUrl = $mediaItem ? \App\Models\Content\Post::mediaItemUrl($mediaItem) : '';
 $isVideo = $mediaItem ? \App\Models\Content\Post::mediaItemIsVideo($mediaItem) : false;
 $excerpt = \Illuminate\Support\Str::limit(strip_tags((string) ($post->body_html ?: $post->body)), $loop->first ? 180 : 100);
 $itemClasses = $slotClasses($loop->index);
 @endphp
 <article class="{{ $itemClasses }} group min-h-0 overflow-hidden rounded-[var(--radius-card)] border border-whisker/40 bg-bark shadow-card" role="listitem" data-ui="profile-portfolio-item">
 @if ($canOpenPosts)
 <a href="{{ route('posts.show', ['post' => $post]) }}" class="relative block h-full min-h-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 @else
 <div class="relative block h-full min-h-0">
 @endif
 @if ($mediaUrl !== '')
 @if ($isVideo)
 <video src="{{ $mediaUrl }}" class="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]" muted playsinline preload="metadata" aria-label="{{ $displayName }} portfolio video"></video>
 @else
 <img src="{{ $mediaUrl }}" alt="{{ $displayName }} portfolio post media" class="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]" loading="lazy">
 @endif
 @else
 <div class="absolute inset-0 {{ $profileUser->profile_default_gradient }}"></div>
 @endif

 <div class="absolute inset-0 bg-linear-to-t from-bark via-bark/45 to-bark/5"></div>
 <div class="absolute inset-x-0 bottom-0 space-y-3 p-4 text-warm-white sm:p-5">
 <div class="flex flex-wrap gap-2 text-xs font-semibold">
 <span class="rounded-full bg-warm-white/18 px-2.5 py-1">{{ number_format((int) $post->reactions_count) }} reactions</span>
 <span class="rounded-full bg-warm-white/18 px-2.5 py-1">{{ number_format((int) $post->comments_count) }} comments</span>
 </div>
 <p class="{{ $loop->first ? 'text-xl sm:text-2xl' : 'text-base' }} line-clamp-3 font-display font-bold leading-tight">{{ $excerpt }}</p>
 @if ($post->pet)
 <p class="text-xs font-semibold text-warm-white/80">Featuring {{ $post->pet->name }}</p>
 @endif
 </div>
 @if ($canOpenPosts)
 </a>
 @else
 </div>
 @endif
 </article>
 @endforeach
 </div>
 </section>
 @endif
 </div>
</x-app-layout>
