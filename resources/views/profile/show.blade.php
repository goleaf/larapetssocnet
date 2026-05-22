@php
 $avatarUrl = $profileUser->avatar_url;
 $coverUrl = $profileUser->coverImageUrl();
 $coverPosition = $profileUser->coverPhotoPositionPercentage();
 $isOwner = auth()->check() && auth()->id() === $profileUser->id;
 $hasBlockingRelationship = ($isBlocked ?? false) || ($isBlockedBy ?? false);
 $canInteract = auth()->check() && ! $isOwner && ! $hasBlockingRelationship;
 $profileViewer = auth()->user();
 $profileFollowStatus = (string) ($followStatus ?? 'none');
 $profileOwnerFollowsViewer = (bool) ($profileOwnerFollowsViewer ?? false);
 if ($profileViewer instanceof \App\Models\Identity\User && ! $isOwner) {
 $profileFollowStatus = $profileViewer->getFollowStatus($profileUser);
 $profileOwnerFollowsViewer = $profileUser->isFollowing($profileViewer);
 }
 $profileCanMessage = (bool) ($canMessage ?? false) || (
 $profileViewer instanceof \App\Models\Identity\User
 && ! $isOwner
 && $profileFollowStatus === 'following'
 && $profileOwnerFollowsViewer
 );
 $displayName = $profileUser->display_name ?: $profileUser->name;
 $profileBio = trim((string) ($profileUser->bio ?? ''));
 $location = ($canViewLocation ?? false) ? ($profileUser->location ?? $profileUser->city ?? null) : null;
 $socialLinks = ($canViewContent ?? false) && is_array($profileUser->social_links ?? null) ? $profileUser->social_links : [];

 $websiteRaw = ($canViewContent ?? false) ? trim((string) ($profileUser->website ??'')) : '';
 $websiteUrl = $websiteRaw !==''
 ? (\Illuminate\Support\Str::startsWith($websiteRaw, ['http://','https://']) ? $websiteRaw :'https://'. $websiteRaw)
 : null;
 $websiteDisplay = $websiteUrl
 ? \Illuminate\Support\Str::of($websiteUrl)->replaceStart('https://', '')->replaceStart('http://', '')->before('/')->toString()
 : null;
 $joinedDate = optional($profileUser->created_at)->format('F Y');
 $profilePostsCount = (int) ($profileUser->posts_count ?? 0);
 $profilePetsCount = (int) ($profileUser->pets_count ?? 0);
 $profileFollowersCount = (int) ($profileStats['followers'] ?? $profileUser->followers_count ?? 0);
 $profileFollowingCount = (int) ($profileStats['following'] ?? $profileUser->following_count ?? 0);
 $followersModalPreview = $followersModalPreview ?? collect();
 $followingModalPreview = $followingModalPreview ?? collect();
 $hasProfileStats = ($canViewFollowers ?? false) || ($canViewFollowing ?? false) || ($canViewPets ?? false);
 $hasProfileActions = $isOwner || $canInteract || (! auth()->check() && Route::has('login'));
 $profileUrl = $profileUser->profile_url;
 $profilePrimaryFollowLabel = ($profileVisibility ?? 'public') === 'public' ? 'Follow' : 'Request to Follow';
 $profileMessageUrl = ($profileCanMessage && Route::has('messages.conversation')) ? route('messages.conversation', ['peer'=> $profileUser]) : false;
 $profilePotentialMessageUrl = (($profileCanMessage || $profileOwnerFollowsViewer) && Route::has('messages.conversation')) ? route('messages.conversation', ['peer'=> $profileUser]) : false;
 $profileShareText = 'Meet '.$displayName.' on PetSocial: '.$profileUrl;
 $encodedProfileUrl = rawurlencode($profileUrl);
 $encodedProfileShareText = rawurlencode($profileShareText);
 $encodedProfileShareSubject = rawurlencode('PetSocial profile for '.$displayName);
 $isNewProfileState = ! filled($profileUser->bio)
 && ! filled($profileUser->headline)
 && ! $location
 && ! $websiteUrl
 && $profilePostsCount === 0
 && $profilePetsCount === 0;

 $tabItems = [
 ['label'=>'Posts','value'=>'posts','count'=> $profilePostsCount],
 ['label'=>'About','value'=>'about','href'=>'#profile-intro'],
 ];

 if ($canViewPets ?? false) {
 $tabItems[] = ['label'=>'Pets','value'=>'pets','count'=> $profilePetsCount];
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
 <div class="w-full min-w-0 space-y-5" data-ui="profile-shell" x-data="profileActions({
 followStatus: @js($profileFollowStatus),
 isFollowing: @js($profileFollowStatus === 'following'),
 isBlocked: @js($isBlocked),
 isBlockedBy: @js($isBlockedBy ?? false),
 followersCount: @js($profileUser->followers_count),
 followUrl: @js(route('users.follow', ['user'=> $profileUser])),
 unfollowUrl: @js(route('users.unfollow', ['user'=> $profileUser])),
 blockUrl: @js(route('users.block', ['user'=> $profileUser])),
 unblockUrl: @js(route('users.unblock', ['user'=> $profileUser]))
 })">

 <section class="w-full min-w-0 overflow-hidden rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white shadow-card"
 data-ui="profile-header"
 aria-labelledby="profile-header-title"
 x-data="{
 position: @js($coverPosition),
 savedPosition: @js($coverPosition),
 repositioning: false,
 dragging: false,
 startY: 0,
 startPosition: @js($coverPosition),
 savingCoverPosition: false,
 coverNotice: '',
 clampPosition(value) {
 return Math.min(100, Math.max(0, Number(value) || 0));
 },
 coverPointerY(event) {
 if (event.touches && event.touches.length > 0) return event.touches[0].clientY;
 if (event.changedTouches && event.changedTouches.length > 0) return event.changedTouches[0].clientY;
 return event.clientY ?? this.startY;
 },
 beginRepositioning() {
 this.savedPosition = this.position;
 this.startPosition = this.position;
 this.repositioning = true;
 this.coverNotice = '';
 },
 startCoverDrag(event) {
 if (!this.repositioning) return;
 event.preventDefault();
 this.dragging = true;
 this.startY = this.coverPointerY(event);
 this.startPosition = this.position;
 },
 moveCover(event) {
 if (!this.dragging) return;
 event.preventDefault();
 const currentY = this.coverPointerY(event);
 const bannerHeight = Math.max(1, this.$refs.coverBanner?.clientHeight || 280);
 const deltaPercent = ((currentY - this.startY) / bannerHeight) * 100;
 this.position = this.clampPosition(this.startPosition - deltaPercent);
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
 const savedPosition = await $wire.saveCoverPosition(this.position);
 this.position = this.clampPosition(savedPosition ?? this.position);
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
 @mousemove.window="moveCover($event)"
 @mouseup.window="stopCoverDrag()"
 @touchmove.window="moveCover($event)"
 @touchend.window="stopCoverDrag()"
 @touchcancel.window="stopCoverDrag()">
 <div data-ui="profile-hero" class="relative">
 <div data-ui="profile-cover-banner" x-ref="coverBanner" class="relative h-[140px] w-full overflow-hidden md:h-[180px] lg:h-[280px]">
 @if ($coverUrl)
 <img data-ui="profile-cover-image" src="{{ $coverUrl }}" alt="{{ $profileUser->name }} cover image"
 class="absolute inset-0 h-full w-full select-none object-cover"
 style="object-position: center {{ $coverPosition }}%"
 x-bind:style="`object-position: center ${position}%`"
 x-bind:class="repositioning ? (dragging ? 'cursor-grabbing touch-none' : 'cursor-grab touch-none') : ''"
 draggable="false"
 @mousedown="startCoverDrag($event)"
 @touchstart="startCoverDrag($event)"/>
 @else
 <div data-ui="profile-cover-fallback" class="absolute inset-0 {{ $profileUser->profile_default_gradient }}"></div>
 @endif

 <div class="absolute inset-0 bg-bark/35"></div>

 <div class="absolute left-4 right-4 top-4 flex items-center justify-between gap-2 sm:left-auto sm:justify-end">
 @if ($isOwner && $coverUrl)
 <div class="flex items-center gap-2">
 <button data-ui="cover-reposition-trigger" x-show="!repositioning" type="button" @click="beginRepositioning()"
 class="inline-flex min-h-10 items-center rounded-[var(--radius-control)] bg-warm-white/90 px-3 py-2 text-xs font-semibold text-bark shadow-sm transition-colors hover:bg-warm-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 Reposition cover
 </button>
 <div data-ui="cover-reposition-actions" x-show="repositioning" x-cloak class="flex items-center gap-2">
 <button type="button" @click="saveCoverPosition()" x-bind:disabled="savingCoverPosition"
 x-bind:aria-busy="savingCoverPosition.toString()"
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

 <div data-ui="profile-avatar" class="absolute left-4 -bottom-[45px] z-10 flex h-[90px] w-[90px] items-center justify-center overflow-hidden rounded-full border-4 border-white bg-warm-white shadow-card lg:left-6 lg:-bottom-[60px] lg:h-[120px] lg:w-[120px]">
 @if ($avatarUrl)
 <img data-ui="profile-avatar-image" src="{{ $avatarUrl }}" alt="{{ $displayName }} profile avatar" class="h-full w-full object-cover" loading="lazy">
 @else
 <div data-ui="profile-avatar-initial" class="flex h-full w-full items-center justify-center {{ $profileUser->profile_default_avatar_color }}" role="img" aria-label="{{ $displayName }} generated avatar">
 <span class="font-display text-4xl font-bold uppercase lg:text-5xl" aria-hidden="true">{{ $profileUser->profile_initial }}</span>
 </div>
 @endif
 </div>
 </div>

 <div class="px-4 pb-5 pt-14 sm:px-6 lg:pt-16">
 <p x-show="coverNotice" x-cloak class="pt-3 text-sm font-semibold text-fur" x-text="coverNotice"></p>
 <div data-ui="profile-header-identity" class="flex max-w-3xl flex-col gap-2">
 <div class="pb-1">
 <div class="flex flex-wrap items-center gap-2">
 <h1 id="profile-header-title" data-ui="profile-display-name" class="text-3xl font-bold font-display text-bark">{{ $displayName }}</h1>
 @if ($profileUser->profile_verified)
 <x-ui.verified-badge tooltip-id="profile-header-verified-tooltip"/>
 @endif
 </div>
 <p data-ui="profile-username" class="text-sm font-medium text-fur">&#64;{{ $profileUser->username }}</p>

 @if ($profileBio !== '')
 <div
 data-ui="profile-header-bio"
 class="mt-2"
 x-data="{
 expanded: false,
 canToggle: false,
 collapsedHeight: 84,
 expandedHeight: 84,
 measureBio() {
 const bio = this.$refs.bioText;
 if (!bio) return;
 const lineHeight = parseFloat(getComputedStyle(bio).lineHeight) || 28;
 this.collapsedHeight = Math.round(lineHeight * 3);
 this.expandedHeight = bio.scrollHeight;
 this.canToggle = this.expandedHeight > this.collapsedHeight + 2;
 },
 bioStyle() {
 return this.canToggle ? `max-height: ${this.expanded ? this.expandedHeight : this.collapsedHeight}px` : '';
 },
 toggleBio() {
 this.measureBio();
 this.expanded = !this.expanded;
 this.$nextTick(() => this.measureBio());
 },
 }"
 x-init="measureBio()"
 @resize.window.debounce.150ms="measureBio()">
 <p
 id="profile-header-bio"
 x-ref="bioText"
 data-ui="profile-bio-text"
 class="max-w-3xl overflow-hidden whitespace-pre-line text-base leading-7 text-bark transition-[max-height] duration-300 ease-out line-clamp-3"
 x-bind:class="canToggle && !expanded ? 'line-clamp-3' : 'line-clamp-none'"
 x-bind:style="bioStyle()">{{ $profileBio }}</p>
 <button
 data-ui="profile-bio-toggle"
 x-show="canToggle"
 x-cloak
 type="button"
 class="mt-1 inline text-sm font-semibold text-paw underline underline-offset-4 transition-colors hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 x-bind:aria-expanded="expanded.toString()"
 aria-controls="profile-header-bio"
 @click="toggleBio()">
 <span x-text="expanded ? 'Read less' : 'Read more'">Read more</span>
 </button>
 </div>
 @endif

 @if ($location || ($websiteUrl && $websiteDisplay) || $joinedDate)
 <ul data-ui="profile-metadata"
 class="mt-3 flex flex-col gap-2 text-sm text-fur sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-4 sm:gap-y-2"
 role="list"
 aria-label="Profile metadata">
 @if ($location)
 <li data-ui="profile-metadata-location" class="inline-flex min-h-7 items-center gap-1.5">
 <svg class="h-4 w-4 shrink-0 text-fur" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
 <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s6-5.7 6-11a6 6 0 1 0-12 0c0 5.3 6 11 6 11Z"/>
 <circle cx="12" cy="10" r="2.4"/>
 </svg>
 <span>{{ $location }}</span>
 </li>
 @endif
 @if ($websiteUrl && $websiteDisplay)
 <li data-ui="profile-metadata-website" class="inline-flex min-h-7 min-w-0 items-center gap-1.5">
 <svg class="h-4 w-4 shrink-0 text-fur" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
 <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 13.5a3 3 0 0 0 4.2 0l3.3-3.3a3 3 0 0 0-4.2-4.2l-.8.8"/>
 <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5a3 3 0 0 0-4.2 0L6 13.8A3 3 0 0 0 10.2 18l.8-.8"/>
 </svg>
 <a href="{{ $websiteUrl }}"
 target="_blank"
 rel="noopener noreferrer"
 class="min-w-0 truncate font-semibold text-paw transition-colors hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 {{ $websiteDisplay }}
 </a>
 </li>
 @endif
 @if ($joinedDate)
 <li data-ui="profile-metadata-joined" class="inline-flex min-h-7 items-center gap-1.5">
 <svg class="h-4 w-4 shrink-0 text-fur" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
 <path stroke-linecap="round" stroke-linejoin="round" d="M7 3v3M17 3v3M4.5 8h15M6.5 5h11A2.5 2.5 0 0 1 20 7.5v10A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5v-10A2.5 2.5 0 0 1 6.5 5Z"/>
 </svg>
 <span>Joined {{ $joinedDate }}</span>
 </li>
 @endif
 </ul>
 @endif
 </div>
 </div>

 @if ($hasProfileStats || $hasProfileActions)
 <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-stretch lg:justify-between" data-ui="profile-stats-actions">
 @if ($hasProfileStats)
 <ul class="grid grid-cols-3 gap-3 lg:flex-1 lg:self-stretch" role="list"
 data-ui="profile-stats"
 aria-label="Profile statistics">
 @if ($canViewFollowers ?? false)
 <li role="listitem">
 <button type="button"
 data-ui="profile-stat-card"
 data-stat="followers"
 aria-haspopup="dialog"
 aria-controls="profile-followers-modal"
 @click="window.toggleModal('profile-followers-modal')"
 class="group flex min-h-20 w-full flex-col items-center justify-center rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white px-3 py-2 text-center transition-all hover:-translate-y-0.5 hover:bg-cream hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <p class="font-display text-2xl font-bold leading-none text-bark" x-text="formatCount(followersCount)">
 {{ number_format($profileFollowersCount) }}</p>
 <p class="text-xs text-fur group-hover:text-bark">Followers</p>
 </button>
 </li>
 @endif
 @if ($canViewFollowing ?? false)
 <li role="listitem">
 <button type="button"
 data-ui="profile-stat-card"
 data-stat="following"
 aria-haspopup="dialog"
 aria-controls="profile-following-modal"
 @click="window.toggleModal('profile-following-modal')"
 class="group flex min-h-20 w-full flex-col items-center justify-center rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white px-3 py-2 text-center transition-all hover:-translate-y-0.5 hover:bg-cream hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <p class="font-display text-2xl font-bold leading-none text-bark">{{ number_format($profileFollowingCount) }}</p>
 <p class="text-xs text-fur group-hover:text-bark">Following</p>
 </button>
 </li>
 @endif
 @if ($canViewPets ?? false)
 <li role="listitem">
 <a href="{{ route('profile.show', ['user'=> $profileUser,'tab'=>'pets']) }}#profile-tabs"
 data-ui="profile-stat-card"
 data-stat="pets"
 aria-controls="profile-tabs"
 @click.prevent="$wire.activateTab('pets').then(() => $nextTick(() => document.getElementById('profile-tabs')?.scrollIntoView({ behavior: 'smooth', block: 'start' })))"
 class="group flex min-h-20 w-full flex-col items-center justify-center rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white px-3 py-2 text-center transition-all hover:-translate-y-0.5 hover:bg-cream hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <p class="font-display text-2xl font-bold leading-none text-bark">{{ number_format($profilePetsCount) }}</p>
 <p class="text-xs text-fur group-hover:text-bark">Pets</p>
 </a>
 </li>
 @endif
 </ul>
 @endif

 @if ($hasProfileActions)
 <div class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center lg:w-auto lg:min-w-[18rem] lg:justify-end" data-ui="profile-actions">
 @if ($isOwner)
 <div class="grid w-full grid-cols-2 gap-2 sm:w-auto sm:min-w-[18rem]" data-ui="profile-owner-actions">
 <x-ui.button type="button" variant="primary" size="sm" class="min-h-11"
 aria-haspopup="dialog"
 aria-controls="profile-edit-modal"
 @click="window.toggleModal('profile-edit-modal')">Edit Profile</x-ui.button>
 <x-ui.button type="button" variant="secondary" size="sm" class="min-h-11"
 aria-haspopup="dialog"
 aria-controls="profile-share-modal"
 @click="window.toggleModal('profile-share-modal')">Share Profile</x-ui.button>
 </div>
 @elseif ($canInteract)
 <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center" data-ui="profile-visitor-actions" x-data="{ followingHovered: false, showUnfollowSheet: false }" @keydown.escape.window="showUnfollowSheet = false">
 <button
 data-ui="profile-follow-primary-action"
 data-follow-status="{{ $profileFollowStatus }}"
 class="hidden min-h-11 min-w-[10rem] items-center justify-center rounded-[var(--radius-control)] px-5 py-2 text-sm font-semibold transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-60 md:inline-flex"
 :class="followButtonClass"
 x-bind:disabled="busy || hasBlockingRelationship || followStatus === 'pending'" x-bind:aria-pressed="(followStatus === 'following').toString()"
 x-bind:aria-label="followStatus === 'following' ? @js('Unfollow '.$profileUser->name) : (followStatus === 'pending' ? @js('Requested to follow '.$profileUser->name) : @js($profilePrimaryFollowLabel.' '.$profileUser->name))"
 @mouseenter="followingHovered = followStatus === 'following'"
 @mouseleave="followingHovered = false"
 @click="toggleFollow">
 <span x-text="busy ? 'Saving...' : (followStatus === 'following' ? (followingHovered ? 'Unfollow' : 'Following') : (followStatus === 'none' ? @js($profilePrimaryFollowLabel) : followLabel))">{{ $profileFollowStatus === 'following' ? 'Following' : $profilePrimaryFollowLabel }}</span>
 </button>

 <button
 type="button"
 data-ui="profile-follow-mobile-action"
 data-follow-status="{{ $profileFollowStatus }}"
 class="inline-flex min-h-11 min-w-[10rem] items-center justify-center rounded-[var(--radius-control)] px-5 py-2 text-sm font-semibold transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw disabled:cursor-not-allowed disabled:opacity-60 md:hidden"
 :class="followButtonClass"
 x-bind:disabled="busy || hasBlockingRelationship || followStatus === 'pending'"
 x-bind:aria-pressed="(followStatus === 'following').toString()"
 x-bind:aria-label="followStatus === 'following' ? @js('Manage following '.$profileUser->name) : (followStatus === 'pending' ? @js('Requested to follow '.$profileUser->name) : @js($profilePrimaryFollowLabel.' '.$profileUser->name))"
 @click="if (followStatus === 'following') { showUnfollowSheet = true; return; } toggleFollow()">
 <span x-text="busy ? 'Saving...' : (followStatus === 'none' ? @js($profilePrimaryFollowLabel) : followLabel)">{{ $profileFollowStatus === 'following' ? 'Following' : $profilePrimaryFollowLabel }}</span>
 </button>

 @if ($profilePotentialMessageUrl)
 <x-ui.button
 :href="$profilePotentialMessageUrl"
 variant="secondary"
 size="sm"
 class="min-h-11 sm:min-w-28"
 data-ui="profile-mutual-message-action"
 x-show="followStatus === 'following' && @js($profileOwnerFollowsViewer)"
 x-cloak>Message</x-ui.button>
 @endif

 @include('profile._actions-dropdown', ['user'=> $profileUser,'isBlocked'=> $isBlocked,'profileUrl'=> $profileUrl,'messageUrl'=> $profileMessageUrl])

 <div
 x-show="showUnfollowSheet && followStatus === 'following'"
 x-cloak
 x-transition.opacity
 data-ui="profile-unfollow-bottom-sheet"
 class="fixed inset-0 z-50 md:hidden"
 role="dialog"
 aria-modal="true"
 aria-labelledby="profile-unfollow-sheet-title"
 >
 <button type="button" class="absolute inset-0 h-full w-full bg-bark/40" aria-label="Keep following {{ $profileUser->name }}" @click="showUnfollowSheet = false"></button>
 <div class="absolute inset-x-0 bottom-0 rounded-t-[var(--radius-card)] border border-whisker/40 bg-warm-white p-4 shadow-card">
 <p id="profile-unfollow-sheet-title" class="text-base font-semibold text-bark">Unfollow &#64;{{ $profileUser->username }}?</p>
 <p class="mt-1 text-sm text-fur">Their public updates will stop appearing in your following feed.</p>
 <div class="mt-4 grid gap-2">
 <button
 type="button"
 data-ui="profile-unfollow-confirm-action"
 class="inline-flex min-h-11 w-full items-center justify-center rounded-[var(--radius-control)] bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-rose-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 @click="showUnfollowSheet = false; toggleFollow()">
 Unfollow
 </button>
 <button
 type="button"
 data-ui="profile-unfollow-keep-action"
 class="inline-flex min-h-11 w-full items-center justify-center rounded-[var(--radius-control)] border border-whisker/40 bg-warm-white px-4 py-2 text-sm font-semibold text-bark transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 @click="showUnfollowSheet = false">
 Keep Following
 </button>
 </div>
 </div>
 </div>
 </div>

 <button
 x-show="followStatus === 'pending'"
 @click="cancelRequest"
 type="button"
 class="inline-flex min-h-11 items-center rounded-[var(--radius-soft)] text-xs font-semibold text-fur underline transition-colors hover:text-red-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 >
 Cancel request
 </button>
 @elseif (!auth()->check() && Route::has('profile.guest-follow'))
 <form method="POST" action="{{ route('profile.guest-follow', ['user'=> $profileUser]) }}" data-ui="profile-guest-follow-form" class="w-full sm:w-auto">
 @csrf
 <x-ui.button type="submit" variant="primary" size="sm" class="min-h-11 sm:min-w-28" data-ui="profile-guest-follow-action">Follow</x-ui.button>
 </form>
 @endif
 </div>
 @endif
 </div>
 @endif

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

 <div class="mt-5 border-t border-whisker/30 pt-4" data-ui="profile-identity-panel">
 <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
 <div class="min-w-0">
 @if (($profileUser->headline || $profileUser->pronouns) && ! $isNewProfileState)
 <div class="max-w-3xl space-y-1">
 @if ($profileUser->headline)
 <p data-ui="profile-headline" class="text-sm font-semibold text-bark">{{ $profileUser->headline }}</p>
 @endif
 @if ($profileUser->pronouns)
 <p data-ui="profile-pronouns" class="text-sm text-fur">{{ $profileUser->pronouns }}</p>
 @endif
 </div>
 @elseif ($profileBio === '' && $isOwner)
 <p class="max-w-3xl text-base leading-7 text-fur">Add a short introduction so visitors know the person behind the profile.</p>
 @elseif ($profileBio === '' && $isNewProfileState)
 <p class="max-w-3xl text-base leading-7 text-fur" data-ui="profile-new-state">New member. {{ $displayName }} is getting settled in.</p>
 @endif
 </div>

 <ul class="flex flex-wrap gap-2 lg:max-w-md lg:justify-end" role="list" aria-label="Profile highlights">
 @if ($canViewPets ?? false)
 <li data-ui="profile-identity-chip"
 class="inline-flex min-h-9 items-center gap-1 rounded-[var(--radius-soft)] border border-whisker/40 bg-cream px-3 text-xs font-semibold text-bark">
 <span aria-hidden="true">🐾</span>
 <span>{{ number_format($profilePetsCount) }} {{ Str::plural('pet', $profilePetsCount) }}</span>
 </li>
 @endif
 @if ($canViewContent ?? false)
 <li data-ui="profile-identity-chip"
 class="inline-flex min-h-9 items-center gap-1 rounded-[var(--radius-soft)] border border-whisker/40 bg-cream px-3 text-xs font-semibold text-bark">
 <span aria-hidden="true">✍</span>
 <span>{{ number_format($profilePostsCount) }} {{ Str::plural('post', $profilePostsCount) }}</span>
 </li>
 @endif
 </ul>
 </div>
 </div>

 @guest
 @if (Route::has('register'))
 <div class="mt-4 flex flex-col gap-3 rounded-[var(--radius-soft)] border border-paw-light bg-paw-light/20 px-4 py-3 sm:flex-row sm:items-center sm:justify-between" data-ui="profile-guest-cta">
 <p class="text-sm font-medium text-bark">Join PetSocial to follow {{ $displayName }} and start your own profile.</p>
 <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
 <x-ui.button :href="route('register')" variant="primary" size="sm" class="min-h-11">Join PetSocial</x-ui.button>
 <x-ui.button :href="route('login')" variant="outline" size="sm" class="min-h-11">Log In</x-ui.button>
 </div>
 </div>
 @endif
 @endguest
 </div>
 </section>

 @if ($canViewFollowers ?? false)
 <x-ui.modal id="profile-followers-modal" name="profile-followers-modal" title="Followers"
 :description="number_format($profileFollowersCount).' '.Str::plural('follower', $profileFollowersCount)"
 size="lg"
 data-ui="profile-followers-modal">
 <div class="max-h-[28rem] overflow-y-auto pr-1" data-ui="profile-followers-modal-list">
 @forelse ($followersModalPreview as $follower)
 <a href="{{ route('profile.show', ['user'=> $follower]) }}"
 data-ui="profile-followers-modal-user"
 class="flex min-h-16 items-center gap-3 rounded-[var(--radius-soft)] px-3 py-2 transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <x-ui.avatar :src="$follower->avatar_url" :name="$follower->name" size="md"/>
 <span class="min-w-0">
 <span class="block truncate text-sm font-semibold text-bark">{{ $follower->name }}</span>
 <span class="block truncate text-xs text-fur">&#64;{{ $follower->username }}</span>
 </span>
 </a>
 @empty
 <x-ui.empty-state icon="" title="No followers yet" description="Followers will appear here." class="py-10"/>
 @endforelse
 </div>

 <x-slot name="footer">
 <x-ui.button :href="route('profile.followers', ['user'=> $profileUser])" variant="outline" size="sm" class="min-h-11">
 View all followers
 </x-ui.button>
 </x-slot>
 </x-ui.modal>
 @endif

 @if ($canViewFollowing ?? false)
 <x-ui.modal id="profile-following-modal" name="profile-following-modal" title="Following"
 :description="number_format($profileFollowingCount).' following'"
 size="lg"
 data-ui="profile-following-modal">
 <div class="max-h-[28rem] overflow-y-auto pr-1" data-ui="profile-following-modal-list">
 @forelse ($followingModalPreview as $followedUser)
 <a href="{{ route('profile.show', ['user'=> $followedUser]) }}"
 data-ui="profile-following-modal-user"
 class="flex min-h-16 items-center gap-3 rounded-[var(--radius-soft)] px-3 py-2 transition-colors hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <x-ui.avatar :src="$followedUser->avatar_url" :name="$followedUser->name" size="md"/>
 <span class="min-w-0">
 <span class="block truncate text-sm font-semibold text-bark">{{ $followedUser->name }}</span>
 <span class="block truncate text-xs text-fur">&#64;{{ $followedUser->username }}</span>
 </span>
 </a>
 @empty
 <x-ui.empty-state icon="" title="Not following anyone yet" description="Profiles followed by this user will appear here." class="py-10"/>
 @endforelse
 </div>

 <x-slot name="footer">
 <x-ui.button :href="route('profile.following', ['user'=> $profileUser])" variant="outline" size="sm" class="min-h-11">
 View all following
 </x-ui.button>
 </x-slot>
 </x-ui.modal>
 @endif

 @if ($isOwner)
 <x-ui.modal id="profile-edit-modal" name="profile-edit-modal" title="Edit Profile"
 description="Update the public details people see on your profile."
 size="xl"
 data-ui="profile-edit-modal">
 <form action="{{ route('settings.profile.update') }}" method="POST" class="space-y-5" data-ui="profile-edit-modal-form">
 @csrf
 @method('PUT')
 <input type="hidden" name="username" value="{{ $profileUser->username }}">
 <input type="hidden" name="email" value="{{ $profileUser->email }}">

 <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
 <x-ui.input id="profile_modal_name" name="name" label="Name" :value="old('name', $profileUser->name)" required autocomplete="name"/>
 <x-ui.input id="profile_modal_display_name" name="display_name" label="Display name" :value="old('display_name', $profileUser->display_name)" autocomplete="nickname"/>
 <div class="sm:col-span-2">
 <x-ui.textarea id="profile_modal_bio" name="bio" rows="4" label="Bio" :value="old('bio', $profileUser->bio)" maxlength="1000"
 hint="Brief description for your profile."/>
 </div>
 <div class="sm:col-span-2">
 <x-ui.input id="profile_modal_headline" name="headline" label="Headline" :value="old('headline', $profileUser->headline)"
 hint="Short status or tagline shown near your name."/>
 </div>
 <x-ui.input id="profile_modal_location" name="location" label="Location" :value="old('location', $profileUser->location)"/>
 <x-ui.input id="profile_modal_website" name="website" type="url" label="Website" :value="old('website', $profileUser->website)"/>
 </div>

 <div class="flex flex-col gap-2 border-t border-whisker/30 pt-5 sm:flex-row sm:items-center sm:justify-between">
 <x-ui.button :href="route('settings.profile')" variant="ghost" size="sm" class="min-h-11">Advanced settings</x-ui.button>
 <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
 <x-ui.button type="button" variant="outline" size="sm" class="min-h-11" @click="window.toggleModal('profile-edit-modal', false)">Cancel</x-ui.button>
 <x-ui.button type="submit" variant="primary" size="sm" class="min-h-11">Save Profile</x-ui.button>
 </div>
 </div>
 </form>
 </x-ui.modal>

 <x-ui.modal id="profile-share-modal" name="profile-share-modal" title="Share Profile"
 description="Copy your profile link, scan the QR code, or send it to another platform."
 size="lg"
 data-ui="profile-share-modal">
 <div class="space-y-5"
 data-ui="profile-share-panel"
 x-data="{
 profileUrl: @js($profileUrl),
 shareText: @js($profileShareText),
 copiedLabel: '',
 copyTimer: null,
 async copy(value, label) {
 try {
 if (navigator.clipboard?.writeText) {
 await navigator.clipboard.writeText(value);
 } else {
 this.$refs.clipboardFallback.value = value;
 this.$refs.clipboardFallback.select();
 document.execCommand('copy');
 }

 this.copiedLabel = label;
 window.clearTimeout(this.copyTimer);
 this.copyTimer = window.setTimeout(() => { this.copiedLabel = ''; }, 2000);
 } catch (error) {
 this.copiedLabel = 'copy failed';
 }
 },
 }">
 <textarea x-ref="clipboardFallback" class="sr-only" tabindex="-1" aria-hidden="true" readonly></textarea>

 <div class="space-y-2">
 <label for="profile-share-url" class="text-sm font-semibold text-bark">Profile URL</label>
 <div class="flex flex-col gap-2 sm:flex-row">
 <input id="profile-share-url" data-ui="profile-share-url" type="text" readonly value="{{ $profileUrl }}"
 class="form-input min-h-11 flex-1 text-sm"
 @focus="$el.select()">
 <x-ui.button type="button" variant="primary" size="sm" class="min-h-11 sm:min-w-28"
 data-ui="profile-share-copy-button"
 @click="copy(profileUrl, 'profile link')">
 <span x-text="copiedLabel === 'profile link' ? 'Copied' : 'Copy Link'">Copy Link</span>
 </x-ui.button>
 </div>
 <p class="text-xs text-fur" role="status" aria-live="polite" x-show="copiedLabel" x-text="copiedLabel === 'copy failed' ? 'Copy failed. Select the URL and copy it manually.' : 'Copied ' + copiedLabel + '.'"></p>
 </div>

 <div class="grid gap-4 sm:grid-cols-[12rem_minmax(0,1fr)] sm:items-center">
 <div data-ui="profile-share-qr" class="mx-auto flex h-48 w-48 items-center justify-center rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white p-3 shadow-sm sm:mx-0">
 <img
 data-ui="profile-share-qr-code"
 src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=12&data={{ $encodedProfileUrl }}"
 alt="QR code for {{ '@'.$profileUser->username }} profile URL"
 class="h-full w-full"
 loading="lazy"
 referrerpolicy="no-referrer">
 </div>
 <div class="space-y-2">
 <h4 class="font-display text-lg font-semibold text-bark">QR code</h4>
 <p class="text-sm leading-6 text-fur">Use the QR code when sharing your profile from printed material, event tables, or another screen.</p>
 </div>
 </div>

 <div data-ui="profile-share-options" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
 <button type="button"
 class="btn-base btn-secondary min-h-11 px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 data-ui="profile-share-copy-text"
 @click="copy(shareText, 'share text')">
 <span x-text="copiedLabel === 'share text' ? 'Copied' : 'Copy share text'">Copy share text</span>
 </button>
 <a href="https://twitter.com/intent/tweet?text={{ $encodedProfileShareText }}"
 target="_blank"
 rel="noopener noreferrer"
 class="btn-base btn-outline min-h-11 px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 data-ui="profile-share-x">Share on X</a>
 <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedProfileUrl }}"
 target="_blank"
 rel="noopener noreferrer"
 class="btn-base btn-outline min-h-11 px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 data-ui="profile-share-facebook">Share on Facebook</a>
 <a href="mailto:?subject={{ $encodedProfileShareSubject }}&body={{ $encodedProfileShareText }}"
 class="btn-base btn-outline min-h-11 px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 data-ui="profile-share-email">Email profile</a>
 </div>
 </div>
 </x-ui.modal>

 @endif

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

 <x-ui.card id="profile-tabs" padding="sm" data-ui="profile-tabs" class="sticky top-20 z-30 scroll-mt-24 bg-warm-white">
 <x-ui.tabs :tabs="$tabItems" :active="$tab" class="mb-0"/>
 </x-ui.card>

 <div class="grid gap-5 lg:grid-cols-[20rem_minmax(0,1fr)]">
 <aside class="space-y-5">
 <x-ui.card id="profile-intro" data-ui="profile-intro-card">
 <h2 class="text-base font-bold font-display text-bark">Intro</h2>

 <div class="mt-3 space-y-2 text-sm text-fur">
 @if ($profileUser->bio)
 <p class="whitespace-pre-line text-bark">{{ $profileUser->bio }}</p>
 @elseif ($isOwner)
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
