<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\ProfileVisibilityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

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
     *     canViewPhotos: bool,
     *     photos: Collection<int, array{key: string, url: string, alt: string, post: Post, posted_at: string}>
     * }
     */
    public function viewData(): array
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();
        $canViewPhotos = app(ProfileVisibilityService::class)->canViewFullProfile($viewer, $profileUser);

        return [
            'profileUser' => $profileUser,
            'canViewPhotos' => $canViewPhotos,
            'photos' => $canViewPhotos ? $this->postPhotos($profileUser, $viewer) : collect(),
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
     * @return Collection<int, array{key: string, url: string, alt: string, post: Post, posted_at: string}>
     */
    private function postPhotos(User $profileUser, ?User $viewer): Collection
    {
        return Post::profilePhotoGridPosts($profileUser, $viewer)
            ->flatMap(function (Post $post) use ($profileUser): Collection {
                return $post->mediaItemsForDisplay()
                    ->filter(fn (mixed $mediaItem): bool => Post::mediaItemIsPhoto($mediaItem))
                    ->map(function (mixed $mediaItem) use ($post, $profileUser): ?array {
                        $url = Post::mediaItemUrl($mediaItem);

                        if ($url === '') {
                            return null;
                        }

                        $postedAt = $post->created_at?->format('M j, Y') ?? '';

                        return [
                            'key' => $this->photoKey($post, $mediaItem),
                            'url' => $url,
                            'alt' => __('Photo from :name\'s post', ['name' => $profileUser->name]),
                            'post' => $post,
                            'posted_at' => $postedAt,
                        ];
                    })
                    ->filter()
                    ->values();
            })
            ->values();
    }

    private function photoKey(Post $post, mixed $mediaItem): string
    {
        $mediaKey = is_object($mediaItem) && method_exists($mediaItem, 'getKey')
            ? (string) $mediaItem->getKey()
            : md5(Post::mediaItemUrl($mediaItem));

        $mediaType = is_object($mediaItem)
            ? Str::kebab(class_basename($mediaItem::class))
            : 'media';

        return sprintf('profile-photo-%s-%s-%s', $post->getKey(), $mediaType, $mediaKey);
    }
};
?>

@placeholder
<div data-ui="profile-tab-panel-loading" id="profile-panel-photos" aria-busy="true">
 <x-ui.card>
 <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
 @for ($index = 0; $index < 8; $index++)
 <div class="aspect-square animate-pulse rounded-[var(--radius-soft)] bg-cream"></div>
 @endfor
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
 @if ($data['photos']->isNotEmpty())
 <div data-ui="profile-photos-grid" class="grid grid-cols-2 gap-2 lg:grid-cols-3">
 @foreach ($data['photos'] as $photo)
 <figure
 wire:key="{{ $photo['key'] }}"
 data-ui="profile-photo-grid-item"
 class="group relative aspect-square overflow-hidden rounded-[var(--radius-soft)] border border-whisker/30 bg-cream"
 >
 <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" class="h-full w-full object-cover transition-[scale] duration-200 lg:group-hover:scale-[1.02]" loading="lazy">
 <figcaption class="pointer-events-none absolute inset-0 hidden items-center justify-center bg-bark/0 text-white opacity-0 transition-[background-color,opacity] duration-150 lg:flex lg:group-hover:bg-bark/45 lg:group-hover:opacity-100">
 <span class="inline-flex items-center gap-3 rounded-full bg-bark/60 px-3 py-1.5 text-xs font-semibold shadow-sm backdrop-blur-sm">
 <span>{{ number_format((int) ($photo['post']->reactions_count ?? $photo['post']->likes_count ?? 0)) }} reactions</span>
 <span>{{ number_format((int) ($photo['post']->comments_count ?? 0)) }} comments</span>
 </span>
 </figcaption>
 </figure>
 @endforeach
 </div>
 @else
 <x-ui.empty-state icon="📷" title="No photos yet"
 description="When this user shares visible post photos, they will appear here."/>
 @endif
 </x-ui.card>
 @else
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="Photos are private"
 description="This profile does not share photos with your current access level."/>
 </x-ui.card>
 @endif
</div>
