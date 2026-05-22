<?php

use App\Models\Identity\User;
use App\Models\Pets\PhotoGallery;
use App\Services\ProfileVisibilityService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

new class extends Component
{
    public int $profileUserId;

    public function mount(int $profileUserId): void
    {
        $this->profileUserId = $profileUserId;
    }

    /**
     * @return array{
     *     profileUser: User,
     *     isOwner: bool,
     *     canViewPhotos: bool,
     *     galleries: Collection<int, PhotoGallery>,
     *     photos: Collection<int, Media>
     * }
     */
    public function viewData(): array
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();
        $isOwner = $viewer instanceof User && $viewer->is($profileUser);
        $canViewPhotos = app(ProfileVisibilityService::class)->canViewFullProfile($viewer, $profileUser);

        return [
            'profileUser' => $profileUser,
            'isOwner' => $isOwner,
            'canViewPhotos' => $canViewPhotos,
            'galleries' => $canViewPhotos ? $this->galleries($profileUser) : collect(),
            'photos' => $canViewPhotos ? $this->photos($profileUser) : collect(),
        ];
    }

    private function profileUser(): User
    {
        return User::query()
            ->whereKey($this->profileUserId)
            ->with('media')
            ->firstOrFail();
    }

    private function viewer(): ?User
    {
        $viewer = auth()->user();

        return $viewer instanceof User ? $viewer : null;
    }

    /**
     * @return Collection<int, PhotoGallery>
     */
    private function galleries(User $user): Collection
    {
        return $user->photoGalleries()
            ->with(['coverMedia', 'media'])
            ->withCount('media')
            ->latest()
            ->get();
    }

    /**
     * @return Collection<int, Media>
     */
    private function photos(User $user): Collection
    {
        return collect($user->getMedia(User::MEDIA_COLLECTION_PHOTOS))
            ->merge($user->getMedia(User::MEDIA_COLLECTION_AVATAR))
            ->merge($user->getMedia(User::MEDIA_COLLECTION_COVER))
            ->values();
    }
};
?>

@placeholder
<div data-ui="profile-tab-panel-loading" id="profile-panel-photos" aria-busy="true">
 <x-ui.card>
 <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
 <div class="h-44 animate-pulse rounded-xl bg-cream"></div>
 <div class="h-44 animate-pulse rounded-xl bg-cream"></div>
 <div class="h-44 animate-pulse rounded-xl bg-cream"></div>
 </div>
 </x-ui.card>
</div>
@endplaceholder

@php
 $data = $this->viewData();
@endphp

<div data-ui="profile-tab-panel" id="profile-panel-photos">
 @if ($data['canViewPhotos'])
 <x-ui.card>
 <div class="space-y-6">
 @if ($data['isOwner'])
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

 @if ($data['galleries']->isNotEmpty())
 <div class="space-y-3">
 <div class="flex items-center justify-between">
 <h3 class="text-sm font-semibold text-bark">Galleries</h3>
 @if ($data['isOwner'])
 <a href="{{ route('settings.photos') }}"
 class="inline-flex min-h-10 items-center rounded-[var(--radius-soft)] text-xs font-semibold text-paw hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Manage</a>
 @endif
 </div>
 <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
 @foreach ($data['galleries'] as $gallery)
 <a href="{{ route('photo-galleries.show', ['user'=> $data['profileUser'],'gallery'=> $gallery]) }}"
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

 @if ($data['isOwner'])
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
 @if ($data['isOwner'])
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
 @forelse ($data['photos'] as $photo)
 <img src="{{ $photo->getUrl() }}" alt="{{ $data['profileUser']->name }} photo"
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
 @else
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="Photos are private"
 description="This profile does not share photos with your current access level."/>
 </x-ui.card>
 @endif
</div>
