@php
 $avatarUrl = $profileUser->avatar_url;
 $coverUrl = $profileUser->coverImageUrl();
 $coverPosition = (float) ($profileUser->cover_photo_position ?? 50);
 $isOwner = auth()->check() && auth()->id() === $profileUser->id;
 $hasBlockingRelationship = ($isBlocked ?? false) || ($isBlockedBy ?? false);
 $canInteract = auth()->check() && ! $isOwner && ! $hasBlockingRelationship;
 $displayName = $profileUser->display_name ?: $profileUser->name;
 $location = ($canViewLocation ?? false) ? ($profileUser->location ?? $profileUser->city ?? null) : null;
 $socialLinks = ($canViewContent ?? false) && is_array($profileUser->social_links ?? null) ? $profileUser->social_links : [];

 $websiteRaw = ($canViewContent ?? false) ? trim((string) ($profileUser->website ??'')) : '';
 $websiteUrl = $websiteRaw !==''
 ? (\Illuminate\Support\Str::startsWith($websiteRaw, ['http://','https://']) ? $websiteRaw :'https://'. $websiteRaw)
 : null;

 $tabItems = [
 ['label'=>'Posts','value'=>'posts','count'=> (int) ($profileUser->posts_count ?? 0)],
 ['label'=>'About','value'=>'about','href'=>'#profile-intro'],
 ];

 if ($canViewPets ?? false) {
 $tabItems[] = ['label'=>'Pets','value'=>'pets','count'=> (int) ($profileUser->pets_count ?? 0)];
 }

 if ($canViewPhotos ?? false) {
 $tabItems[] = ['label'=>'Photos','value'=>'photos','count'=> $sidebarPhotos->count()];
 }

 if ($canViewGroups ?? false) {
 $tabItems[] = ['label'=>'Groups','value'=>'groups'];
 }

 if ($canViewContent ?? false) {
 $tabItems[] = ['label'=>'Events','value'=>'events'];
 $tabItems[] = ['label'=>'Contests','value'=>'contests'];
 }

 if ($canViewFollowers ?? false) {
 $tabItems[] = ['label'=>'Followers','value'=>'followers-nav','href'=> route('profile.followers', ['user'=> $profileUser]),'count'=> (int) ($profileUser->followers_count ?? 0)];
 }

 if ($canViewFollowing ?? false) {
 $tabItems[] = ['label'=>'Following','value'=>'following-nav','href'=> route('profile.following', ['user'=> $profileUser]),'count'=> (int) ($profileUser->following_count ?? 0)];
 }

 if ($canViewLikes ?? false) {
 $tabItems[] = ['label'=>'Likes','value'=>'likes'];
 }

 if ($isOwner) {
 $tabItems[] = ['label'=>'Scheduled','value'=>'scheduled','count'=> (int) ($scheduledCount ?? 0)];
 }
@endphp

@section('title','@'. $profileUser->username .'— PetSocial')
@php
 $metaDescription = $profileUser->bio ?: ($displayName ."'s profile on PetSocial");
@endphp
@push('meta')
 <meta property="og:type" content="profile">
 <meta property="og:title" content="{{ $displayName }} ({{'@'. $profileUser->username }})">
 <meta property="og:description" content="{{ $metaDescription }}">
 <meta property="og:url" content="{{ $profileUser->profile_url }}">
 <meta property="profile:username" content="{{ $profileUser->username }}">
 <meta name="twitter:card" content="summary">
 <meta name="twitter:title" content="{{ $displayName }} on PetSocial">
 <meta name="twitter:description" content="{{ $metaDescription }}">
 <link rel="canonical" href="{{ $profileUser->profile_url }}">
 @if ($profileVisibility === 'private')
 <meta name="robots" content="noindex, nofollow">
 @endif
@endpush

<x-app-layout>
 <div class="space-y-5" data-ui="profile-shell" x-data="profileActions({
 followStatus: @js($followStatus),
 isFollowing: @js($isFollowing),
 isBlocked: @js($isBlocked),
 isBlockedBy: @js($isBlockedBy ?? false),
 followersCount: @js($profileUser->followers_count),
 followUrl: @js(route('users.follow', ['user'=> $profileUser])),
 unfollowUrl: @js(route('users.unfollow', ['user'=> $profileUser])),
 blockUrl: @js(route('users.block', ['user'=> $profileUser])),
 unblockUrl: @js(route('users.unblock', ['user'=> $profileUser]))
 })">

 <section class="overflow-hidden rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white shadow-card"
 data-ui="profile-hero"
 x-data="{
 position: @js($coverPosition),
 savedPosition: @js($coverPosition),
 repositioning: false,
 dragging: false,
 startY: 0,
 startPosition: @js($coverPosition),
 savingCoverPosition: false,
 coverNotice: '',
 startCoverDrag(event) {
 if (!this.repositioning) return;
 this.dragging = true;
 this.startY = event.clientY;
 this.startPosition = this.position;
 },
 moveCover(event) {
 if (!this.dragging) return;
 const next = this.startPosition + ((event.clientY - this.startY) / 2);
 this.position = Math.min(100, Math.max(0, next));
 },
 stopCoverDrag() {
 this.dragging = false;
 },
 cancelCoverPosition() {
 this.position = this.savedPosition;
 this.repositioning = false;
 this.coverNotice = '';
 },
 async saveCoverPosition() {
 this.savingCoverPosition = true;
 try {
 const response = await fetch(@js(route('profile.cover-position.update')), {
 method: 'PATCH',
 headers: {
 'Accept': 'application/json',
 'Content-Type': 'application/json',
 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
 },
 body: JSON.stringify({ position: this.position }),
 });
 if (!response.ok) throw new Error('save_failed');
 const data = await response.json();
 this.position = data.position ?? this.position;
 this.savedPosition = this.position;
 this.repositioning = false;
 this.coverNotice = 'Cover position saved.';
 } catch (error) {
 this.position = this.savedPosition;
 this.coverNotice = 'Cover position could not be saved.';
 } finally {
 this.savingCoverPosition = false;
 }
 },
 }"
 @pointermove.window="moveCover($event)"
 @pointerup.window="stopCoverDrag()"
 @pointercancel.window="stopCoverDrag()">
 <div class="relative h-36 w-full sm:h-44 lg:h-[280px]">
 @if ($coverUrl)
 <img src="{{ $coverUrl }}" alt="{{ $profileUser->name }} cover image"
 class="h-full w-full select-none object-cover"
 x-bind:style="`object-position: center ${position}%`"
 x-bind:class="repositioning ? 'cursor-grab active:cursor-grabbing' : ''"
 @pointerdown.prevent="startCoverDrag($event)"/>
 @else
 <div class="h-full w-full {{ $profileUser->profile_default_gradient }}"></div>
 @endif

 <div class="absolute inset-0 bg-bark/35"></div>

 <div class="absolute left-4 right-4 top-4 flex items-center justify-between gap-2 sm:left-auto sm:justify-end">
 @if ($isOwner && $coverUrl)
 <div class="flex items-center gap-2">
 <button x-show="!repositioning" type="button" @click="repositioning = true; coverNotice = ''"
 class="inline-flex min-h-10 items-center rounded-[var(--radius-control)] bg-warm-white/90 px-3 py-2 text-xs font-semibold text-bark shadow-sm transition-colors hover:bg-warm-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 Reposition cover
 </button>
 <div x-show="repositioning" x-cloak class="flex items-center gap-2">
 <button type="button" @click="saveCoverPosition()" x-bind:disabled="savingCoverPosition"
 class="inline-flex min-h-10 items-center rounded-[var(--radius-control)] bg-paw px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:opacity-60">
 <span x-text="savingCoverPosition ? 'Saving...' : 'Save position'"></span>
 </button>
 <button type="button" @click="cancelCoverPosition()"
 class="inline-flex min-h-10 items-center rounded-[var(--radius-control)] bg-warm-white/90 px-3 py-2 text-xs font-semibold text-bark shadow-sm transition-colors hover:bg-warm-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 Cancel
 </button>
 </div>
 </div>
 @elseif ($isOwner)
 <x-ui.button :href="route('settings.profile')" variant="default" size="sm" class="min-h-11">Update Cover</x-ui.button>
 @endif
 <x-ui.badge variant="{{ $profileVisibility === 'public' ? 'success' : 'warning' }}" size="sm" aria-label="Profile visibility">
 {{ $profileVisibilityIcon }} {{ $profileVisibilityLabel }}
 </x-ui.badge>
 </div>
 @if ($isOwner && $coverUrl)
 <p x-show="repositioning" x-cloak class="absolute bottom-4 left-4 rounded-full bg-black/55 px-3 py-1 text-xs font-semibold text-white">
 Drag the cover up or down to choose the best crop.
 </p>
 @endif
 </div>

 <div class="px-4 pb-5 sm:px-6">
 <p x-show="coverNotice" x-cloak class="pt-3 text-sm font-semibold text-fur" x-text="coverNotice"></p>
 <div class="-mt-16 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
 <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
 <x-ui.avatar :src="$avatarUrl" :name="$profileUser->name" size="2xl"
class="h-28 w-28 border-4 border-warm-white bg-warm-white"/>

 <div class="pb-1">
 <div class="flex flex-wrap items-center gap-2">
 <h1 class="text-3xl font-bold font-display text-bark">{{ $displayName }}</h1>
 @if ($profileUser->profile_verified)
 <span class="relative inline-flex" x-data="{ open: false }">
 <button type="button"
 class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-sky-light text-paw shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 @mouseenter="open = true" @mouseleave="open = false" @click="open = !open"
 aria-label="Verified PetSocial account">
 <span aria-hidden="true">🐾</span>
 </button>
 <span x-show="open" x-cloak x-transition
 class="absolute left-1/2 top-10 z-20 w-64 -translate-x-1/2 rounded-[var(--radius-soft)] border border-whisker/40 bg-warm-white px-3 py-2 text-xs font-medium text-bark shadow-card">
 This account has been verified by PetSocial as a notable pet-related account or organization.
 </span>
 </span>
 @endif
 </div>
 <p class="text-sm font-semibold text-fur">&#64;{{ $profileUser->username }}</p>

 <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-fur">
 @if ($profileUser->headline)
 <span class="font-medium text-bark">{{ $profileUser->headline }}</span>
 @endif
 @if ($profileUser->pronouns)
 <span>• {{ $profileUser->pronouns }}</span>
 @endif
 @if ($location)
 <span>📍 {{ $location }}</span>
 @endif
 <span>Joined {{ optional($profileUser->created_at)->format('M Y') }}</span>
 </div>
 </div>
 </div>

 <div class="flex flex-wrap items-center gap-2" data-ui="profile-actions">
 @if ($isOwner)
 <x-ui.button :href="route('posts.create')" variant="secondary" size="sm" class="min-h-11">Create Post</x-ui.button>
 <x-ui.button :href="route('settings.profile')" variant="primary" size="sm" class="min-h-11">Edit Profile</x-ui.button>
 <x-ui.button :href="route('settings.data')" variant="outline" size="sm" class="min-h-11">Account Settings</x-ui.button>
 @elseif ($canInteract)
 <button
 class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] px-4 py-2 text-sm font-medium transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-60"
 :class="followButtonClass"
 x-bind:disabled="busy || hasBlockingRelationship || followStatus === 'pending'" x-bind:aria-pressed="(followStatus === 'following').toString()"
 x-bind:aria-label="followStatus === 'following' ?'Unfollow {{ addslashes($profileUser->name) }}': (followStatus === 'pending' ?'Requested to follow {{ addslashes($profileUser->name) }}' :'Follow {{ addslashes($profileUser->name) }}')"
 @click="toggleFollow">
 <span x-text="busy ?'Saving...': followLabel"></span>
 </button>

 <button
 x-show="followStatus === 'pending'"
 @click="cancelRequest"
 type="button"
 class="inline-flex min-h-11 items-center rounded-[var(--radius-soft)] text-xs font-semibold text-fur underline transition-colors hover:text-red-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 >
 Cancel request
 </button>

 @if ($canMessage ?? false)
 <x-ui.button :href="route('messages.conversation', ['peer'=> $profileUser])" variant="outline"
 size="sm" class="min-h-11 sm:min-w-28">Message</x-ui.button>
 @endif

 @include('profile._actions-dropdown', ['user'=> $profileUser,'isBlocked'=> $isBlocked])
 @elseif (!auth()->check() && Route::has('login'))
 <x-ui.button :href="route('login')" variant="primary" size="sm" class="min-h-11">Sign In to Follow</x-ui.button>
 @endif
 </div>
 </div>

 <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" role="list"
 data-ui="profile-stats"
 aria-label="Profile statistics">
 @if ($canViewFollowers ?? false)
 <a href="{{ route('profile.followers', ['user'=> $profileUser]) }}"
 role="listitem"
 data-ui="profile-stat-card"
 class="group flex min-h-20 flex-col items-center justify-center rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white px-3 py-2 text-center transition-all hover:-translate-y-0.5 hover:bg-cream hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <p class="text-xl font-bold text-bark" x-text="formatCount(followersCount)">
 {{ number_format((int) $profileUser->followers_count) }}</p>
 <p class="text-xs text-fur group-hover:text-bark">Followers</p>
 </a>
 @endif
 @if ($canViewFollowing ?? false)
 <a href="{{ route('profile.following', ['user'=> $profileUser]) }}"
 role="listitem"
 data-ui="profile-stat-card"
 class="group flex min-h-20 flex-col items-center justify-center rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white px-3 py-2 text-center transition-all hover:-translate-y-0.5 hover:bg-cream hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <p class="text-xl font-bold text-bark">{{ number_format((int) $profileUser->following_count) }}
 </p>
 <p class="text-xs text-fur group-hover:text-bark">Following</p>
 </a>
 @endif
 @if ($canViewPets ?? false)
 <a href="{{ route('profile.show', ['user'=> $profileUser,'tab'=>'pets']) }}"
 role="listitem"
 data-ui="profile-stat-card"
 class="group flex min-h-20 flex-col items-center justify-center rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white px-3 py-2 text-center transition-all hover:-translate-y-0.5 hover:bg-cream hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <p class="text-xl font-bold text-bark">{{ number_format((int) $profileUser->pets_count) }}</p>
 <p class="text-xs text-fur group-hover:text-bark">Pets</p>
 </a>
 @endif
 @if ($canViewContent ?? false)
 <a href="{{ route('profile.show', ['user'=> $profileUser,'tab'=>'posts']) }}"
 role="listitem"
 data-ui="profile-stat-card"
 class="group flex min-h-20 flex-col items-center justify-center rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white px-3 py-2 text-center transition-all hover:-translate-y-0.5 hover:bg-cream hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <p class="text-xl font-bold text-bark">
 {{ number_format((int) ($profileUser->posts_count ?? 0)) }}</p>
 <p class="text-xs text-fur group-hover:text-bark">Posts</p>
 </a>
 @endif
 <div class="flex min-h-20 flex-col items-center justify-center rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white px-3 py-2 text-center" role="listitem" data-ui="profile-stat-card">
 <p class="text-xl font-bold text-bark">{{ $profileVisibilityLabel }}</p>
 <p class="text-xs text-fur">Visibility</p>
 </div>
 </div>

 @if ($isOwner && is_array($profileViewStats ?? null))
 <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-fur" data-ui="profile-view-analytics">
 <span class="relative inline-flex items-center gap-1" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @click="open = !open">
 <span aria-hidden="true">👁</span>
 <span>{{ number_format((int) $profileViewStats['current']) }} profile visits in the last 30 days</span>
 <span x-show="open" x-cloak x-transition
 class="absolute left-0 top-7 z-20 w-40 rounded-[var(--radius-soft)] border border-whisker/40 bg-warm-white px-3 py-2 text-xs font-medium text-bark shadow-card">
 Only you can see this.
 </span>
 </span>
 @if (($profileViewStats['trend_percent'] ?? null) !== null)
 <span @class([
 'font-semibold',
 'text-emerald-700'=> ($profileViewStats['trend_direction'] ?? 'up') === 'up',
 'text-amber-700'=> ($profileViewStats['trend_direction'] ?? 'up') === 'down',
 ])>
 {{ ($profileViewStats['trend_direction'] ?? 'up') === 'up' ? '↑' : '↓' }}
 {{ abs((int) $profileViewStats['trend_percent']) }}% from last month
 </span>
 @endif
 </div>
 @endif

 <p class="mt-3 text-sm text-fur" role="status" aria-live="polite" x-show="notice" x-text="notice"></p>
 </div>
 </section>

 @if ($isOwner)
 @php
 $profileCompleteness = (int) ($profileCompletenessPercentage ?? $profileUser->profile_completeness_percentage);
 $completionMissingItems = $profileCompletenessMissingItems ?? $profileUser->profile_completeness_missing_items;
 $completionColor = $profileCompleteness >= 80 ? 'bg-emerald-500' : ($profileCompleteness >= 50 ? 'bg-amber-500' : 'bg-sky-500');
 $showCompletedCard = $profileCompleteness === 100
 && $profileUser->profile_completed_at
 && $profileUser->profile_completed_at->greaterThanOrEqualTo(now()->subDays(7));
 @endphp
 <x-ui.card data-ui="profile-completeness">
 @if ($showCompletedCard)
 <div class="flex items-center gap-3">
 <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-paw-light text-lg" aria-hidden="true">🎉</span>
 <div>
 <h2 class="text-base font-bold font-display text-bark">Your profile is complete!</h2>
 <p class="text-sm text-fur">Your public profile has all core identity details filled in.</p>
 </div>
 </div>
 @else
 <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
 <div class="min-w-0">
 <h2 class="text-base font-bold font-display text-bark">Complete your profile</h2>
 <p class="mt-1 text-sm text-fur">Add the details that help other pet owners recognize and trust you.</p>
 </div>
 <span class="text-sm font-bold text-bark">{{ $profileCompleteness }}%</span>
 </div>
 <div class="mt-4 h-3 overflow-hidden rounded-full bg-cream" x-data="{ width: 0 }" x-init="$nextTick(() => { width = @js($profileCompleteness) })">
 <div class="h-full rounded-full {{ $completionColor }} transition-[width] duration-[600ms] ease-out" x-bind:style="`width: ${width}%`"></div>
 </div>
 @if ($completionMissingItems !== [])
 <div class="mt-4 flex flex-wrap gap-2">
 @foreach ($completionMissingItems as $item)
 <a href="{{ route('settings.profile') }}#{{ $item['key'] }}"
 class="inline-flex min-h-9 items-center rounded-full border border-whisker/40 bg-warm-white px-3 text-xs font-semibold text-fur transition-colors hover:border-paw hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 {{ $item['label'] }}
 </a>
 @endforeach
 </div>
 @endif
 @endif
 </x-ui.card>
 @endif

 {{-- Badge strip --}}
 @if ($badges->isNotEmpty())
 <x-ui.card padding="sm">
 <x-ui.badge-strip :badges="$badges" :max="8"
 :badges-url="route('profile.show', ['user'=> $profileUser,'tab'=>'posts'])"/>
 </x-ui.card>
 @endif

 {{-- Pet showcase (horizontal scroll) --}}
 @if ($canViewContent && $featuredPets->isNotEmpty())
 <x-ui.card>
 <div class="mb-3 flex items-center justify-between">
 <h3 class="text-sm font-semibold text-bark">🐾 Pets</h3>
 <a href="{{ route('profile.show', ['user'=> $profileUser,'tab'=>'pets']) }}"
 class="inline-flex min-h-10 items-center rounded-[var(--radius-soft)] text-xs font-semibold text-paw hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">See all</a>
 </div>
 <div class="-mx-1 flex gap-3 overflow-x-auto pb-2 scroll-smooth snap-x snap-mandatory">
 @foreach ($featuredPets as $pet)
 <x-ui.pet-card :pet="$pet" :owner="$profileUser->name" size="md"/>
 @endforeach
 @if ($isOwner)
 <a href="{{ route('pets.create') }}"
 class="flex min-h-44 w-[160px] flex-shrink-0 snap-start flex-col items-center justify-center rounded-xl border-2 border-dashed border-whisker/40 bg-cream/50 p-3 text-center transition-colors hover:border-paw hover:bg-paw-light/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span class="text-2xl">🐾</span>
 <span class="mt-1 text-xs font-semibold text-paw">Add Pet</span>
 </a>
 @endif
 </div>
 </x-ui.card>
 @elseif ($canViewContent && $isOwner)
 <x-ui.card>
 <div class="flex items-center justify-center gap-3 py-4">
 <a href="{{ route('pets.create') }}"
 class="inline-flex min-h-32 flex-col items-center justify-center rounded-xl border-2 border-dashed border-whisker/40 bg-cream/50 px-6 py-4 text-center transition-colors hover:border-paw hover:bg-paw-light/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span class="text-2xl">🐾</span>
 <p class="mt-1 text-sm font-semibold text-paw">Add your first pet</p>
 </a>
 </div>
 </x-ui.card>
 @endif

 <x-ui.card padding="sm" data-ui="profile-tabs" class="sticky top-20 z-30 bg-warm-white">
 <x-ui.tabs :tabs="$tabItems" :active="$tab" class="mb-0"/>
 </x-ui.card>

 <div class="grid gap-5 lg:grid-cols-[20rem_minmax(0,1fr)]">
 <aside class="space-y-5">
 <x-ui.card id="profile-intro" data-ui="profile-intro-card">
 <h2 class="text-base font-bold font-display text-bark">Intro</h2>

 <div class="mt-3 space-y-2 text-sm text-fur">
 @if ($profileUser->bio)
 <p class="whitespace-pre-line text-bark">{{ $profileUser->bio }}</p>
 @else
 <p>No bio added yet.</p>
 @endif

 @if ($location)
 <p>📍 Lives in {{ $location }}</p>
 @endif

 @if ($websiteUrl)
 <p>
 🔗
 <a href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer"
 class="inline-flex min-h-8 items-center rounded-[var(--radius-soft)] font-medium text-paw hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 {{ $profileUser->website }}
 </a>
 </p>
 @endif

 @if ($socialLinks !== [])
 <div class="space-y-1">
 @foreach ($socialLinks as $label => $url)
 <p>
 🔗
 <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
 class="inline-flex min-h-8 items-center rounded-[var(--radius-soft)] font-medium text-paw hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 {{ \Illuminate\Support\Str::headline((string) $label) }}
 </a>
 </p>
 @endforeach
 </div>
 @endif

 <p>🗓️ Joined {{ optional($profileUser->created_at)->format('F Y') }}</p>
 </div>

 <x-ui.activity-chart :data="$activityData"/>
 </x-ui.card>

 @if ($canViewContent)
 @if ($canViewPets ?? false)
 <x-ui.card>
 <div class="mb-3 flex items-center justify-between gap-2">
 <h3 class="text-sm font-semibold text-bark">Pets</h3>
 <a href="{{ route('profile.show', ['user'=> $profileUser,'tab'=>'pets']) }}"
 class="inline-flex min-h-10 items-center rounded-[var(--radius-soft)] text-xs font-semibold text-paw hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">See all</a>
 </div>

 @if ($featuredPets->isEmpty())
 <p class="text-sm text-fur">No pets published yet.</p>
 @else
 <div class="grid grid-cols-3 gap-2">
 @foreach ($featuredPets as $pet)
 @php
 $petRouteParam = $pet->slug ?? $pet->getKey();
 @endphp
 <a href="{{ route('pets.show', ['pet'=> $petRouteParam]) }}"
 class="block min-h-24 rounded-lg border border-whisker/30 bg-cream p-2 text-center transition-colors hover:bg-paw-light/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <x-ui.avatar :src="$pet->getFirstMediaUrl('avatar')" :name="$pet->name" size="sm"
 class="mx-auto"/>
 <p class="mt-1 truncate text-[11px] font-medium text-bark">{{ $pet->name }}</p>
 </a>
 @endforeach
 </div>
 @endif
 </x-ui.card>
 @endif

 @if ($canViewPhotos ?? false)
 <x-ui.card>
 <div class="mb-3 flex items-center justify-between gap-2">
 <h3 class="text-sm font-semibold text-bark">Photos</h3>
 <a href="{{ route('profile.show', ['user'=> $profileUser,'tab'=>'photos']) }}"
 class="inline-flex min-h-10 items-center rounded-[var(--radius-soft)] text-xs font-semibold text-paw hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">See all</a>
 </div>

 @if ($sidebarPhotos->isEmpty())
 <p class="text-sm text-fur">No photos yet.</p>
 @else
 <div class="grid grid-cols-3 gap-2">
 @foreach ($sidebarPhotos as $photo)
 <a href="{{ route('profile.show', ['user'=> $profileUser,'tab'=>'photos']) }}"
 class="overflow-hidden rounded-lg border border-whisker/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <img src="{{ $photo->getUrl() }}" alt="{{ $profileUser->name }} photo"
 class="h-16 w-full object-cover" loading="lazy"/>
 </a>
 @endforeach
 </div>
 @endif
 </x-ui.card>
 @endif

 @if ($canViewFollowing ?? false)
 <x-ui.card>
 <div class="mb-3 flex items-center justify-between gap-2">
 <h3 class="text-sm font-semibold text-bark">Friends</h3>
 <a href="{{ route('profile.following', ['user'=> $profileUser]) }}"
 class="inline-flex min-h-10 items-center rounded-[var(--radius-soft)] text-xs font-semibold text-paw hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">See all</a>
 </div>

 @if ($friendsPreview->isEmpty())
 <p class="text-sm text-fur">No friends to show yet.</p>
 @else
 <div class="space-y-2">
 @foreach ($friendsPreview as $friend)
 <a href="{{ route('profile.show', ['user'=> $friend]) }}"
 class="flex min-h-14 items-center gap-2 rounded-lg border border-whisker/30 bg-cream px-2 py-2 transition-colors hover:bg-paw-light/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <x-ui.avatar :src="$friend->avatar_url" :name="$friend->name" size="sm"/>
 <div class="min-w-0">
 <p class="truncate text-sm font-medium text-bark">{{ $friend->name }}</p>
 <p class="truncate text-[11px] text-fur">&#64;{{ $friend->username }}</p>
 </div>
 </a>
 @endforeach
 </div>
 @endif
 </x-ui.card>
 @endif

 {{-- Mutual Connections panel (visitor only) --}}
 @if ($mutualConnections->isNotEmpty())
 <x-ui.card>
 <h3 class="mb-3 text-sm font-semibold text-bark">People You Both Follow</h3>
 <x-ui.avatar-group :users="$mutualConnections"/>
 <p class="mt-2 text-xs text-fur">{{ $mutualConnections->count() }}
 {{ Str::plural('person', $mutualConnections->count()) }} you both follow</p>
 </x-ui.card>
 @endif

 {{-- Common Groups panel (visitor only) --}}
 @if ($commonGroups->isNotEmpty())
 <x-ui.card>
 <h3 class="mb-3 text-sm font-semibold text-bark">Groups in Common</h3>
 <div class="space-y-2">
 @foreach ($commonGroups as $group)
 <a href="{{ route('groups.show', ['group'=> $group]) }}"
 class="flex min-h-14 items-center gap-2 rounded-lg border border-whisker/30 bg-cream px-2 py-2 transition-colors hover:bg-paw-light/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <x-ui.avatar :src="$group->getFirstMediaUrl('avatar')" :name="$group->name" size="sm"/>
 <div class="min-w-0">
 <p class="truncate text-sm font-medium text-bark">{{ $group->name }}</p>
 <p class="text-[11px] text-fur">{{ $group->members_count }}
 {{ Str::plural('member', $group->members_count) }}</p>
 </div>
 </a>
 @endforeach
 </div>
 </x-ui.card>
 @endif
 @endif
 </aside>

 <section class="space-y-5">
 @if (!$canViewContent)
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="This profile is private"
 description="Follow {{ $profileUser->name }} to view posts, pets, photos, and likes.">
 @if ($canInteract)
 <x-slot name="action">
 <button
 class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-paw px-4 py-2 text-sm font-medium text-white shadow-button transition-all duration-150 hover:bg-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-60"
 x-bind:disabled="busy || isBlocked" x-bind:aria-pressed="isFollowing.toString()"
 x-bind:aria-label="isFollowing ?'Unfollow {{ addslashes($profileUser->name) }}':'Follow {{ addslashes($profileUser->name) }}'"
 @click="toggleFollow">
 <span
 x-text="busy ?'Saving...': (isFollowing ?'Following':'Follow to View')"></span>
 </button>
 </x-slot>
 @endif
 </x-ui.empty-state>
 </x-ui.card>
 @elseif ($tab ==='pets' && ($canViewPets ?? false))
 <x-ui.card>
 <div class="grid gap-4 sm:grid-cols-2">
 @forelse ($pets as $pet)
 @php
 $petRouteParam = $pet->slug ?? $pet->getKey();
 @endphp
 <a href="{{ route('pets.show', ['pet'=> $petRouteParam]) }}"
 class="block min-h-28 rounded-xl border border-whisker/30 bg-warm-white px-4 py-4 transition-all hover:-translate-y-0.5 hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <div class="flex items-center gap-3">
 <x-ui.avatar :src="$pet->getFirstMediaUrl('avatar')" :name="$pet->name" size="md"/>
 <div class="min-w-0">
 <p class="truncate text-base font-semibold text-bark">{{ $pet->name }}</p>
 <p class="truncate text-xs text-fur">
 {{ $pet->species }}{{ $pet->breed ?'·'. $pet->breed :''}}</p>
 </div>
 </div>
 @if ($pet->bio)
 <p class="mt-3 line-clamp-2 text-sm text-fur">{{ $pet->bio }}</p>
 @endif
 </a>
 @empty
 <div class="col-span-full">
 <x-ui.empty-state icon="🐾" title="No pets yet"
 description="This user has not added pets to their profile."/>
 </div>
 @endforelse
 </div>
 </x-ui.card>
 @elseif ($tab ==='pets')
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="Pets are private"
 description="This profile does not share pet details with your current access level."/>
 </x-ui.card>
 @elseif ($tab ==='photos' && ($canViewPhotos ?? false))
 <x-ui.card>
 <div class="space-y-6">
 @if ($isOwner)
 <div>
 <h3 class="text-sm font-semibold text-bark">Create new gallery</h3>
 <form action="{{ route('photo-galleries.store') }}" method="POST" class="mt-3 space-y-3">
 @csrf
 <div>
 <label for="gallery-title" class="block text-xs font-medium text-fur">Title</label>
 <input id="gallery-title" name="title" type="text"
 class="mt-1 block w-full rounded-md border border-whisker/40 bg-warm-white px-3 py-2 text-sm shadow-sm focus:border-paw focus:outline-none focus:ring-1 focus:ring-paw"
 placeholder="Summer walks, Puppy album..."
 required>
 </div>
 <div>
 <label for="gallery-description" class="block text-xs font-medium text-fur">Description
 (optional)</label>
 <textarea id="gallery-description" name="description" rows="2"
 class="mt-1 block w-full rounded-md border border-whisker/40 bg-warm-white px-3 py-2 text-sm shadow-sm focus:border-paw focus:outline-none focus:ring-1 focus:ring-paw"
 placeholder="Short description for this gallery"></textarea>
 </div>
 <div class="flex justify-end">
 <x-ui.button type="submit" size="sm" variant="primary" class="min-h-11">
 Create Gallery
 </x-ui.button>
 </div>
 </form>
 </div>
 @endif

 @if (isset($galleries) && $galleries->isNotEmpty())
 <div class="space-y-3">
 <div class="flex items-center justify-between">
 <h3 class="text-sm font-semibold text-bark">Galleries</h3>
 @if ($isOwner)
 <a href="{{ route('settings.photos') }}"
 class="inline-flex min-h-10 items-center rounded-[var(--radius-soft)] text-xs font-semibold text-paw hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Manage</a>
 @endif
 </div>
 <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
 @foreach ($galleries as $gallery)
 <a href="{{ route('photo-galleries.show', ['user'=> $profileUser,'gallery'=> $gallery]) }}"
 class="block overflow-hidden rounded-xl border border-whisker/40 bg-warm-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 @php
 $coverUrl = $gallery->coverUrl();
 @endphp
 @if ($coverUrl !=='')
 <img src="{{ $coverUrl }}" alt="{{ $gallery->title }} cover"
 class="h-32 w-full object-cover" loading="lazy"/>
 @else
 <div
 class="flex h-32 w-full items-center justify-center bg-cream text-3xl">
 📷
 </div>
 @endif
 <div class="space-y-2 px-3 pb-3 pt-2">
 <div class="flex items-start justify-between gap-2">
 <div class="min-w-0">
 <p
 class="truncate text-sm font-semibold text-bark">
 {{ $gallery->title }}</p>
 @if ($gallery->description)
 <p
 class="mt-0.5 line-clamp-2 text-xs text-fur">
 {{ $gallery->description }}</p>
 @endif
 </div>
 <x-ui.badge variant="default" size="sm">
 {{ $gallery->media_count }}
 {{ Str::plural('photo', $gallery->media_count) }}
 </x-ui.badge>
 </div>

 @if ($isOwner)
 <form action="{{ route('photo-galleries.photos.store', $gallery) }}"
 method="POST" enctype="multipart/form-data"
 class="mt-1 space-y-2">
 @csrf
 <label class="block text-[11px] font-medium text-fur">
 Add photos
 <input type="file" name="photos[]" multiple
 class="mt-1 block w-full text-[11px] text-fur"
 accept="image/jpeg,image/png,image/webp">
 </label>
 <div class="flex justify-end">
 <x-ui.button type="submit" size="xs" variant="secondary" class="min-h-9">
 Upload
 </x-ui.button>
 </div>
 </form>
 @endif

 @if ($gallery->media->isNotEmpty())
 <div class="mt-2 grid grid-cols-4 gap-1">
 @foreach ($gallery->media->take(8) as $media)
 <div class="relative group">
 <img src="{{ $media->getUrl() }}"
 alt="{{ $gallery->title }} photo"
 class="h-12 w-full rounded object-cover"
 loading="lazy"/>
 @if ($isOwner)
 <form
 action="{{ route('photo-galleries.cover.store', ['gallery'=> $gallery,'media'=> $media]) }}"
 method="POST"
 class="absolute inset-0 flex items-end justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
 @csrf
 <button type="submit"
 class="mb-1 rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-semibold text-bark shadow focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 Set cover
 </button>
 </form>
 @endif
 </div>
 @endforeach
 </div>
 @endif
 </div>
 </a>
 @endforeach
 </div>
 </div>
 @endif

 <div>
 <div class="mb-3 flex items-center justify-between">
 <h3 class="text-sm font-semibold text-bark">All photos</h3>
 </div>
 <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
 @forelse ($photos as $photo)
 <img src="{{ $photo->getUrl() }}" alt="{{ $profileUser->name }} photo"
 class="h-44 w-full rounded-xl object-cover shadow-sm" loading="lazy"/>
 @empty
 <div class="col-span-full">
 <x-ui.empty-state icon="📷" title="No photos yet"
 description="When this user shares photos, they will appear here."/>
 </div>
 @endforelse
 </div>
 </div>
 </div>
 </x-ui.card>
 @elseif ($tab ==='photos')
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="Photos are private"
 description="This profile does not share photos with your current access level."/>
 </x-ui.card>
 @elseif ($tab ==='scheduled' && $isOwner)
 <x-ui.card>
 <h2 class="mb-4 text-base font-bold font-display text-bark">Scheduled posts</h2>
 <div class="space-y-4">
 @forelse (($scheduledPosts ?? collect()) as $post)
 <x-post-card :post="$post" context="profile"/>
 @empty
 <x-ui.empty-state icon="🗓️" title="No scheduled posts" description="Scheduled posts you create will appear here before they publish."/>
 @endforelse
 </div>
 </x-ui.card>
 @elseif ($tab ==='likes' && ($canViewLikes ?? false))
 <x-ui.card>
 <x-ui.empty-state icon="❤️" title="No likes to show"
 description="Likes tab is ready for Wave 2 data integration."/>
 </x-ui.card>
 @elseif ($tab ==='likes')
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="Likes are private"
 description="This profile does not share liked content with your current access level."/>
 </x-ui.card>
 @elseif ($tab ==='groups' && ($canViewGroups ?? false))
 <x-ui.card>
 @if ($groups->isEmpty())
 <x-ui.empty-state icon="🏠" title="Not in any groups yet"
 description="{{ $profileUser->name }} hasn't joined any groups."/>
 @else
 <div class="grid gap-4 sm:grid-cols-2">
 @foreach ($groups as $group)
 <a href="{{ route('groups.show', ['group'=> $group]) }}"
 class="block min-h-24 rounded-xl border border-whisker/30 bg-warm-white px-4 py-4 transition-all hover:-translate-y-0.5 hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <div class="flex items-center gap-3">
 <x-ui.avatar :src="$group->getFirstMediaUrl('avatar')" :name="$group->name" size="md"/>
 <div class="min-w-0">
 <div class="flex items-center gap-1">
 <p class="truncate text-base font-semibold text-bark">{{ $group->name }}</p>
 @php($groupRole = $group->pivot->role?->value ?? $group->pivot->role)
 @if ($groupRole ==='owner')
 <span title="Owner">👑</span>
 @elseif ($groupRole ==='admin')
 <span title="Admin">🛡️</span>
 @endif
 </div>
 <p class="text-xs text-fur">{{ $group->members_count }}
 {{ Str::plural('member', $group->members_count) }}</p>
 </div>
 </div>
 </a>
 @endforeach
 </div>
 @endif
 </x-ui.card>
 @elseif ($tab ==='groups')
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="Groups are private"
 description="This profile does not share groups with your current access level."/>
 </x-ui.card>
 @elseif ($tab ==='events')
 <x-ui.card>
 @if ($upcomingEvents->isEmpty() && $pastEvents->isEmpty())
 <x-ui.empty-state icon="📅" title="No events yet"
 description="{{ $profileUser->name }} hasn't RSVP'd to any events."/>
 @else
 @if ($upcomingEvents->isNotEmpty())
 <h3 class="mb-3 text-sm font-semibold text-bark">Upcoming Events</h3>
 <div class="space-y-3">
 @foreach ($upcomingEvents as $event)
 <a href="{{ route('events.show', ['event'=> $event]) }}"
 class="flex min-h-20 items-center gap-3 rounded-xl border border-whisker/30 bg-warm-white px-4 py-3 transition-all hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <div class="flex-shrink-0 rounded-lg bg-paw-light px-3 py-2 text-center">
 <p class="text-xs font-bold text-paw-dark">{{ optional($event->start_at)->format('M') }}
 </p>
 <p class="text-lg font-bold text-paw">{{ optional($event->start_at)->format('d') }}</p>
 </div>
 <div class="min-w-0">
 <p class="truncate text-sm font-semibold text-bark">{{ $event->title }}</p>
 @if ($event->location_text)
 <p class="truncate text-xs text-fur">📍 {{ $event->location_text }}</p>
 @endif
 @if ($event->pivot)
 <x-ui.badge variant="success" size="sm"
 class="mt-1">{{ ucfirst($event->pivot->status) }}</x-ui.badge>
 @endif
 </div>
 </a>
 @endforeach
 </div>
 @endif

 @if ($pastEvents->isNotEmpty())
 <div class="mt-4" x-data="{ showPast: false }">
 <button @click="showPast = !showPast"
 class="flex min-h-10 items-center gap-1 rounded-[var(--radius-soft)] text-xs font-semibold text-fur transition-colors hover:text-bark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <svg :class="showPast &&'rotate-90'" class="h-3 w-3 transition-transform" fill="none"
 viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
 </svg>
 Past Events ({{ $pastEvents->count() }})
 </button>
 <div x-show="showPast" class="mt-2 space-y-3">
 @foreach ($pastEvents as $event)
 <a href="{{ route('events.show', ['event'=> $event]) }}"
 class="flex min-h-20 items-center gap-3 rounded-xl border border-whisker/20 bg-cream/50 px-4 py-3 opacity-75 transition-all hover:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <div class="flex-shrink-0 rounded-lg bg-whisker/20 px-3 py-2 text-center">
 <p class="text-xs font-bold text-fur">{{ optional($event->start_at)->format('M') }}
 </p>
 <p class="text-lg font-bold text-fur">{{ optional($event->start_at)->format('d') }}
 </p>
 </div>
 <div class="min-w-0">
 <p class="truncate text-sm font-semibold text-bark">{{ $event->title }}</p>
 @if ($event->location_text)
 <p class="truncate text-xs text-fur">📍 {{ $event->location_text }}</p>
 @endif
 </div>
 </a>
 @endforeach
 </div>
 </div>
 @endif
 @endif
 </x-ui.card>
 @elseif ($tab ==='contests')
 <x-ui.card>
 @if ($contestEntries->isEmpty() && $organizedContests->isEmpty())
 <x-ui.empty-state icon="🏆" title="No contests yet"
 description="{{ $profileUser->name }} hasn't entered any contests."/>
 @else
 @if ($organizedContests->isNotEmpty())
 <h3 class="mb-3 text-sm font-semibold text-bark">Organized Contests</h3>
 <div class="mb-4 grid gap-4 sm:grid-cols-2">
 @foreach ($organizedContests as $contest)
 <a href="{{ route('contests.show', ['contest'=> $contest]) }}"
 class="block min-h-24 rounded-xl border border-whisker/30 bg-warm-white px-4 py-4 transition-all hover:-translate-y-0.5 hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <div class="flex items-center justify-between">
 <p class="truncate text-sm font-semibold text-bark">{{ $contest->title }}</p>
 <x-ui.badge variant="info" size="sm">Organizer</x-ui.badge>
 </div>
 <x-ui.badge variant="default" size="sm"
 class="mt-1">{{ ucfirst($contest->status) }}</x-ui.badge>
 </a>
 @endforeach
 </div>
 @endif

 @if ($contestEntries->isNotEmpty())
 <h3 class="mb-3 text-sm font-semibold text-bark">Contest Entries</h3>
 <div class="grid gap-4 sm:grid-cols-2">
 @foreach ($contestEntries as $entry)
 @if ($entry->contest)
 <a href="{{ route('contests.show', ['contest'=> $entry->contest]) }}"
 class="block min-h-24 rounded-xl border {{ $entry->is_winner ?'border-amber ring-2 ring-amber':'border-whisker/30'}} bg-warm-white px-4 py-4 transition-all hover:-translate-y-0.5 hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <div class="flex items-center justify-between">
 <p class="truncate text-sm font-semibold text-bark">{{ $entry->contest->title }}</p>
 @if ($entry->is_winner)
 <x-ui.badge variant="warning" size="sm">🏆 Winner</x-ui.badge>
 @endif
 </div>
 <x-ui.badge variant="default" size="sm"
 class="mt-1">{{ ucfirst($entry->contest->status) }}</x-ui.badge>
 </a>
 @endif
 @endforeach
 </div>
 @endif
 @endif
 </x-ui.card>
 @else
 <section class="space-y-4">
 @if ($isOwner)
 <x-ui.card>
 <div class="flex items-center gap-3">
 <x-ui.avatar :src="$avatarUrl" :name="$profileUser->name" size="md"/>
 <a href="{{ route('posts.create') }}"
 class="flex min-h-11 w-full items-center rounded-full border border-whisker/40 bg-cream px-4 py-2 text-left text-sm text-fur transition-colors hover:bg-paw-light/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 What's on your mind, {{ $profileUser->name }}?
 </a>
 </div>
 <div class="mt-3 grid grid-cols-3 gap-2">
 <a href="{{ route('posts.create') }}"
 class="flex min-h-11 items-center justify-center rounded-lg border border-whisker/30 bg-warm-white px-3 py-2 text-center text-xs font-semibold text-fur transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">📷
 Photo</a>
 <a href="{{ route('posts.create') }}"
 class="flex min-h-11 items-center justify-center rounded-lg border border-whisker/30 bg-warm-white px-3 py-2 text-center text-xs font-semibold text-fur transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">🐾
 Pet update</a>
 <a href="{{ route('posts.create') }}"
 class="flex min-h-11 items-center justify-center rounded-lg border border-whisker/30 bg-warm-white px-3 py-2 text-center text-xs font-semibold text-fur transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">🎉
 Life event</a>
 </div>
 </x-ui.card>
 @endif

 @forelse ($posts as $post)
 <x-post-card :post="$post" context="profile"/>

 @if ($isOwner)
 <div class="-mt-2 flex items-center justify-end gap-2">
 @if ($post->is_pinned)
 <form method="POST" action="{{ route('posts.unpin', $post) }}">
 @csrf
 @method('DELETE')
 <x-ui.button type="submit" variant="ghost" size="xs" class="min-h-9">Unpin</x-ui.button>
 </form>
 @else
 <form method="POST" action="{{ route('posts.pin', $post) }}">
 @csrf
 <x-ui.button type="submit" variant="secondary" size="xs" class="min-h-9">Pin to Profile</x-ui.button>
 </form>
 @endif
 </div>
 @endif
 @empty
 <x-ui.empty-state icon="📝" title="No posts yet" description="No posts published yet."/>
 @endforelse

 @if ($isOwner && ($privateCount ?? 0) > 0)
 <x-ui.card>
 <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-fur">
 <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
 stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
 </svg>
 Private posts
 <x-ui.badge variant="default" size="sm">{{ $privateCount }}</x-ui.badge>
 </h3>

 <div class="space-y-4">
 @foreach (($privatePosts ?? collect()) as $post)
 <x-post-card :post="$post" context="profile"/>
 @endforeach
 </div>
 </x-ui.card>
 @endif

 @if ($isOwner && (($draftCount ?? 0) > 0 || ($scheduledCount ?? 0) > 0))
 <x-ui.card>
 <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-fur">
 <span aria-hidden="true">🗂️</span>
 Drafts & Scheduled
 @if (($draftCount ?? 0) > 0)
 <x-ui.badge variant="default" size="sm">{{ $draftCount }} drafts</x-ui.badge>
 @endif
 @if (($scheduledCount ?? 0) > 0)
 <x-ui.badge variant="warning" size="sm">{{ $scheduledCount }} scheduled</x-ui.badge>
 @endif
 </h3>

 <div class="space-y-4">
 @foreach (($draftPosts ?? collect()) as $post)
 <x-post-card :post="$post" context="profile"/>
 @endforeach

 @foreach (($scheduledPosts ?? collect()) as $post)
 <x-post-card :post="$post" context="profile"/>
 @endforeach
 </div>
 </x-ui.card>
 @endif

 @if (method_exists($posts,'hasPages') && $posts->hasPages())
 <x-ui.card>
 <x-ui.pagination :paginator="$posts"/>
 </x-ui.card>
 @endif
 </section>
 @endif
 </section>
 </div>
 </div>
</x-app-layout>
