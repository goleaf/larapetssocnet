@php
 $avatarUrl = $profileUser->getFirstMediaUrl('avatar');
 $coverUrl = $profileUser->getFirstMediaUrl('cover');
 $canInteract = auth()->check() && auth()->id() !== $profileUser->id;
 $isOwner = auth()->check() && auth()->id() === $profileUser->id;
 $location = $profileUser->location ?? $profileUser->city ?? null;

 $websiteRaw = trim((string) ($profileUser->website ??''));
 $websiteUrl = $websiteRaw !==''
 ? (\Illuminate\Support\Str::startsWith($websiteRaw, ['http://','https://']) ? $websiteRaw :'https://'. $websiteRaw)
 : null;

 $tabItems = [
 ['label'=>'Posts','value'=>'posts','count'=> (int) ($profileUser->posts_count ?? 0)],
 ['label'=>'About','value'=>'about','href'=>'#profile-intro'],
 ['label'=>'Pets','value'=>'pets','count'=> (int) ($profileUser->pets_count ?? 0)],
 ['label'=>'Photos','value'=>'photos','count'=> $canViewContent ? $sidebarPhotos->count() : 0],
 ['label'=>'Groups','value'=>'groups'],
 ['label'=>'Events','value'=>'events'],
 ['label'=>'Contests','value'=>'contests'],
 ['label'=>'Followers','value'=>'followers-nav','href'=> route('profile.followers', ['user'=> $profileUser]),'count'=> (int) ($profileUser->followers_count ?? 0)],
 ['label'=>'Following','value'=>'following-nav','href'=> route('profile.following', ['user'=> $profileUser]),'count'=> (int) ($profileUser->following_count ?? 0)],
 ['label'=>'Likes','value'=>'likes'],
 ];
@endphp

@section('title','@'. $profileUser->username .'— PetSocial')
@php
 $metaDescription = $profileUser->bio ?: ($profileUser->name ."'s profile on PetSocial");
@endphp
@push('meta')
 <meta property="og:type" content="profile">
 <meta property="og:title" content="{{ $profileUser->name }} ({{'@'. $profileUser->username }})">
 <meta property="og:description" content="{{ $metaDescription }}">
 <meta property="og:url" content="{{ $profileUser->profile_url }}">
 <meta property="profile:username" content="{{ $profileUser->username }}">
 <meta name="twitter:card" content="summary">
 <meta name="twitter:title" content="{{ $profileUser->name }} on PetSocial">
 <meta name="twitter:description" content="{{ $metaDescription }}">
 <link rel="canonical" href="{{ $profileUser->profile_url }}">
 @if ($profileUser->is_private)
 <meta name="robots" content="noindex, nofollow">
 @endif
@endpush

<x-app-layout>
 <div class="space-y-5" x-data="profileActions({
 isFollowing: @js($isFollowing),
 isBlocked: @js($isBlocked),
 followersCount: @js($profileUser->followers_count),
 followUrl: @js(route('users.follow', ['user'=> $profileUser])),
 unfollowUrl: @js(route('users.unfollow', ['user'=> $profileUser])),
 blockUrl: @js(route('users.block', ['user'=> $profileUser])),
 unblockUrl: @js(route('users.unblock', ['user'=> $profileUser]))
 })">

 <section class="overflow-hidden rounded-2xl border border-whisker/40 bg-warm-white shadow-card">
 <div class="relative h-56 w-full sm:h-72 lg:h-80">
 @if ($coverUrl)
 <img src="{{ $coverUrl }}" alt="{{ $profileUser->name }} cover image"
 class="h-full w-full object-cover"/>
 @else
 <div class="h-full w-full bg-gradient-to-r from-paw-light via-cream to-sky-light"></div>
 @endif

 <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>

 <div class="absolute right-4 top-4 flex items-center gap-2">
 @if ($isOwner)
 <x-ui.button :href="route('settings.profile')" variant="default" size="xs">Update
 Cover</x-ui.button>
 @endif
 @if ($profileUser->is_private)
 <x-ui.badge variant="warning" size="sm" pill>🔒 Private Profile</x-ui.badge>
 @else
 <x-ui.badge variant="success" size="sm" pill>🌍 Public Profile</x-ui.badge>
 @endif
 </div>
 </div>

 <div class="px-4 pb-5 sm:px-6">
 <div class="-mt-16 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
 <div class="flex items-end gap-4">
 <x-ui.avatar :src="$avatarUrl" :name="$profileUser->name" size="2xl"
 class="h-28 w-28 border-4 border-warm-white shadow-xl bg-warm-white"/>

 <div class="pb-1">
 <h1 class="text-3xl font-bold font-display text-bark">{{ $profileUser->name }}</h1>
 <p class="text-sm font-semibold text-fur">&#64;{{ $profileUser->username }}</p>

 <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-fur">
 @if ($location)
 <span>📍 {{ $location }}</span>
 @endif
 <span>Joined {{ optional($profileUser->created_at)->format('M Y') }}</span>
 </div>
 </div>
 </div>

 <div class="flex flex-wrap items-center gap-2">
 @if ($isOwner)
 <x-ui.button :href="route('posts.create')" variant="secondary" size="sm">Create
 Post</x-ui.button>
 <x-ui.button :href="route('settings.profile')" variant="primary" size="sm">Edit
 Profile</x-ui.button>
 <x-ui.button :href="route('settings.data')" variant="outline" size="sm">Account
 Settings</x-ui.button>
 @elseif ($canInteract)
 <button
 class="inline-flex items-center justify-center rounded-md px-4 py-1.5 text-sm font-medium transition-all duration-150"
 :class="isFollowing ?'border border-whisker bg-warm-white text-bark hover:bg-cream':'bg-paw text-white hover:bg-paw-dark shadow-button'"
 x-bind:disabled="busy || isBlocked" x-bind:aria-pressed="isFollowing.toString()"
 x-bind:aria-label="isFollowing ?'Unfollow {{ addslashes($profileUser->name) }}':'Follow {{ addslashes($profileUser->name) }}'"
 @click="toggleFollow">
 <span x-text="busy ?'Saving...': (isFollowing ?'Following':'Follow')"></span>
 </button>

 <x-ui.button :href="route('messages.conversation', ['peer'=> $profileUser])" variant="outline"
 size="sm">Message</x-ui.button>

 @include('profile._actions-dropdown', ['user'=> $profileUser,'isBlocked'=> $isBlocked])
 @elseif (!auth()->check() && Route::has('login'))
 <x-ui.button :href="route('login')" variant="primary" size="sm">Sign In to Follow</x-ui.button>
 @endif
 </div>
 </div>

 <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" role="list"
 aria-label="Profile statistics">
 <a href="{{ route('profile.followers', ['user'=> $profileUser]) }}"
 class="rounded-xl border border-whisker/30 bg-warm-white px-3 py-2 text-center transition-colors hover:bg-cream">
 <p class="text-xl font-bold text-bark" x-text="formatCount(followersCount)">
 {{ number_format((int) $profileUser->followers_count) }}</p>
 <p class="text-xs text-fur">Followers</p>
 </a>
 <a href="{{ route('profile.following', ['user'=> $profileUser]) }}"
 class="rounded-xl border border-whisker/30 bg-warm-white px-3 py-2 text-center transition-colors hover:bg-cream">
 <p class="text-xl font-bold text-bark">{{ number_format((int) $profileUser->following_count) }}
 </p>
 <p class="text-xs text-fur">Following</p>
 </a>
 <a href="{{ route('profile.show', ['user'=> $profileUser,'tab'=>'pets']) }}"
 class="rounded-xl border border-whisker/30 bg-warm-white px-3 py-2 text-center transition-colors hover:bg-cream">
 <p class="text-xl font-bold text-bark">{{ number_format((int) $profileUser->pets_count) }}</p>
 <p class="text-xs text-fur">Pets</p>
 </a>
 <a href="{{ route('profile.show', ['user'=> $profileUser,'tab'=>'posts']) }}"
 class="rounded-xl border border-whisker/30 bg-warm-white px-3 py-2 text-center transition-colors hover:bg-cream">
 <p class="text-xl font-bold text-bark">
 {{ number_format((int) ($profileUser->posts_count ?? 0)) }}</p>
 <p class="text-xs text-fur">Posts</p>
 </a>
 <div class="rounded-xl border border-whisker/30 bg-warm-white px-3 py-2 text-center">
 <p class="text-xl font-bold text-bark">{{ $profileUser->is_private ?'Private':'Public'}}</p>
 <p class="text-xs text-fur">Visibility</p>
 </div>
 </div>

 <p class="mt-3 text-sm text-fur" role="status" aria-live="polite" x-show="notice" x-text="notice"></p>
 </div>
 </section>

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
 class="text-xs font-semibold text-paw hover:underline">See all</a>
 </div>
 <div class="-mx-1 flex gap-3 overflow-x-auto pb-2 scroll-smooth snap-x snap-mandatory">
 @foreach ($featuredPets as $pet)
 <x-ui.pet-card :pet="$pet" size="md"/>
 @endforeach
 @if ($isOwner)
 <a href="{{ route('pets.create') }}"
 class="flex-shrink-0 w-[160px] rounded-xl border-2 border-dashed border-whisker/40 bg-cream/50 p-3 text-center transition-colors hover:border-paw hover:bg-paw-light/30 snap-start flex flex-col items-center justify-center">
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
 class="rounded-xl border-2 border-dashed border-whisker/40 bg-cream/50 px-6 py-4 text-center transition-colors hover:border-paw hover:bg-paw-light/30">
 <span class="text-2xl">🐾</span>
 <p class="mt-1 text-sm font-semibold text-paw">Add your first pet</p>
 </a>
 </div>
 </x-ui.card>
 @endif

 <x-ui.card padding="sm">
 <x-ui.tabs :tabs="$tabItems" :active="$tab" class="mb-0"/>
 </x-ui.card>

 <div class="grid gap-5 lg:grid-cols-[20rem_minmax(0,1fr)]">
 <aside class="space-y-5">
 <x-ui.card id="profile-intro">
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
 class="font-medium text-paw hover:text-paw-dark hover:underline">
 {{ $profileUser->website }}
 </a>
 </p>
 @endif

 <p>🗓️ Joined {{ optional($profileUser->created_at)->format('F Y') }}</p>
 </div>

 <x-ui.activity-chart :data="$activityData"/>
 </x-ui.card>

 @if ($canViewContent)
 <x-ui.card>
 <div class="mb-3 flex items-center justify-between gap-2">
 <h3 class="text-sm font-semibold text-bark">Pets</h3>
 <a href="{{ route('profile.show', ['user'=> $profileUser,'tab'=>'pets']) }}"
 class="text-xs font-semibold text-paw hover:underline">See all</a>
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
 class="rounded-lg border border-whisker/30 bg-cream p-2 text-center transition-colors hover:bg-paw-light/40">
 <x-ui.avatar :src="$pet->getFirstMediaUrl('avatar')" :name="$pet->name" size="sm"
 class="mx-auto"/>
 <p class="mt-1 truncate text-[11px] font-medium text-bark">{{ $pet->name }}</p>
 </a>
 @endforeach
 </div>
 @endif
 </x-ui.card>

 <x-ui.card>
 <div class="mb-3 flex items-center justify-between gap-2">
 <h3 class="text-sm font-semibold text-bark">Photos</h3>
 <a href="{{ route('profile.show', ['user'=> $profileUser,'tab'=>'photos']) }}"
 class="text-xs font-semibold text-paw hover:underline">See all</a>
 </div>

 @if ($sidebarPhotos->isEmpty())
 <p class="text-sm text-fur">No photos yet.</p>
 @else
 <div class="grid grid-cols-3 gap-2">
 @foreach ($sidebarPhotos as $photo)
 <a href="{{ route('profile.show', ['user'=> $profileUser,'tab'=>'photos']) }}"
 class="overflow-hidden rounded-lg border border-whisker/30">
 <img src="{{ $photo->getUrl() }}" alt="{{ $profileUser->name }} photo"
 class="h-16 w-full object-cover" loading="lazy"/>
 </a>
 @endforeach
 </div>
 @endif
 </x-ui.card>

 <x-ui.card>
 <div class="mb-3 flex items-center justify-between gap-2">
 <h3 class="text-sm font-semibold text-bark">Friends</h3>
 <a href="{{ route('profile.following', ['user'=> $profileUser]) }}"
 class="text-xs font-semibold text-paw hover:underline">See all</a>
 </div>

 @if ($friendsPreview->isEmpty())
 <p class="text-sm text-fur">No friends to show yet.</p>
 @else
 <div class="space-y-2">
 @foreach ($friendsPreview as $friend)
 <a href="{{ route('profile.show', ['user'=> $friend]) }}"
 class="flex items-center gap-2 rounded-lg border border-whisker/30 bg-cream px-2 py-2 transition-colors hover:bg-paw-light/40">
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
 class="flex items-center gap-2 rounded-lg border border-whisker/30 bg-cream px-2 py-2 transition-colors hover:bg-paw-light/40">
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
 class="inline-flex items-center justify-center rounded-md bg-paw px-4 py-2 text-sm font-medium text-white shadow-button transition-all duration-150 hover:bg-paw-dark"
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
 @elseif ($tab ==='pets')
 <x-ui.card>
 <div class="grid gap-4 sm:grid-cols-2">
 @forelse ($pets as $pet)
 @php
 $petRouteParam = $pet->slug ?? $pet->getKey();
 @endphp
                                <a href="{{ route('pets.show', ['pet'=> $petRouteParam]) }}"
 class="rounded-xl border border-whisker/30 bg-warm-white px-4 py-4 transition-all hover:-translate-y-0.5 hover:shadow-card-hover">
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
 @elseif ($tab ==='photos')
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
 <x-ui.button type="submit" size="sm" variant="primary">
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
 class="text-xs font-semibold text-paw hover:underline">Manage</a>
 @endif
 </div>
 <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
 @foreach ($galleries as $gallery)
 <a href="{{ route('photo-galleries.show', ['user'=> $profileUser,'gallery'=> $gallery]) }}"
 class="overflow-hidden rounded-xl border border-whisker/40 bg-warm-white shadow-sm block hover:shadow-card-hover hover:-translate-y-0.5 transition">
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
 <x-ui.badge variant="default" size="sm" pill>
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
 <x-ui.button type="submit" size="xs" variant="secondary">
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
 class="mb-1 rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-semibold text-bark shadow">
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
 @elseif ($tab ==='likes')
 <x-ui.card>
 <x-ui.empty-state icon="❤️" title="No likes to show"
 description="Likes tab is ready for Wave 2 data integration."/>
 </x-ui.card>
 @elseif ($tab ==='groups')
 <x-ui.card>
 @if ($groups->isEmpty())
 <x-ui.empty-state icon="🏠" title="Not in any groups yet"
 description="{{ $profileUser->name }} hasn't joined any groups."/>
 @else
 <div class="grid gap-4 sm:grid-cols-2">
 @foreach ($groups as $group)
 <a href="{{ route('groups.show', ['group'=> $group]) }}"
 class="rounded-xl border border-whisker/30 bg-warm-white px-4 py-4 transition-all hover:-translate-y-0.5 hover:shadow-card-hover">
 <div class="flex items-center gap-3">
 <x-ui.avatar :src="$group->getFirstMediaUrl('avatar')" :name="$group->name" size="md"/>
 <div class="min-w-0">
 <div class="flex items-center gap-1">
 <p class="truncate text-base font-semibold text-bark">{{ $group->name }}</p>
 @if ($group->pivot->role ==='owner')
 <span title="Owner">👑</span>
 @elseif ($group->pivot->role ==='admin')
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
 class="flex items-center gap-3 rounded-xl border border-whisker/30 bg-warm-white px-4 py-3 transition-all hover:shadow-card-hover">
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
 class="flex items-center gap-1 text-xs font-semibold text-fur hover:text-bark transition-colors">
 <svg :class="showPast &&'rotate-90'" class="h-3 w-3 transition-transform" fill="none"
 viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
 </svg>
 Past Events ({{ $pastEvents->count() }})
 </button>
 <div x-show="showPast" x-collapse class="mt-2 space-y-3">
 @foreach ($pastEvents as $event)
 <a href="{{ route('events.show', ['event'=> $event]) }}"
 class="flex items-center gap-3 rounded-xl border border-whisker/20 bg-cream/50 px-4 py-3 opacity-75 transition-all hover:opacity-100">
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
 class="rounded-xl border border-whisker/30 bg-warm-white px-4 py-4 transition-all hover:-translate-y-0.5 hover:shadow-card-hover">
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
 class="rounded-xl border {{ $entry->is_winner ?'border-amber ring-2 ring-amber':'border-whisker/30'}} bg-warm-white px-4 py-4 transition-all hover:-translate-y-0.5 hover:shadow-card-hover">
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
 class="w-full rounded-full border border-whisker/40 bg-cream px-4 py-2 text-left text-sm text-fur transition-colors hover:bg-paw-light/30">
 What's on your mind, {{ $profileUser->name }}?
 </a>
 </div>
 <div class="mt-3 grid grid-cols-3 gap-2">
 <a href="{{ route('posts.create') }}"
 class="rounded-lg border border-whisker/30 bg-warm-white px-3 py-2 text-center text-xs font-semibold text-fur transition-colors hover:bg-cream">📷
 Photo</a>
 <a href="{{ route('posts.create') }}"
 class="rounded-lg border border-whisker/30 bg-warm-white px-3 py-2 text-center text-xs font-semibold text-fur transition-colors hover:bg-cream">🐾
 Pet update</a>
 <a href="{{ route('posts.create') }}"
 class="rounded-lg border border-whisker/30 bg-warm-white px-3 py-2 text-center text-xs font-semibold text-fur transition-colors hover:bg-cream">🎉
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
 <x-ui.button type="submit" variant="ghost" size="xs">Unpin</x-ui.button>
 </form>
 @else
 <form method="POST" action="{{ route('posts.pin', $post) }}">
 @csrf
 <x-ui.button type="submit" variant="secondary" size="xs">Pin to Profile</x-ui.button>
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
 <x-ui.badge variant="default" size="sm" pill>{{ $privateCount }}</x-ui.badge>
 </h3>

 <div class="space-y-4">
 @foreach (($privatePosts ?? collect()) as $post)
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
