<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\ProfileVisibilityService;
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
     *     canViewContent: bool,
     *     canViewLocation: bool,
     *     location: string|null,
     *     websiteUrl: string|null,
     *     websiteDisplay: string|null,
     *     socialLinks: array<string, mixed>,
     *     activityData: list<array{month: string, count: int}>
     * }
     */
    public function viewData(): array
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();
        $visibility = app(ProfileVisibilityService::class);
        $canViewContent = $visibility->canViewFullProfile($viewer, $profileUser);
        $canViewLocation = $visibility->canViewLocation($viewer, $profileUser);
        $location = $canViewLocation ? ($profileUser->location ?? $profileUser->city ?? null) : null;
        $websiteRaw = $canViewContent ? trim((string) ($profileUser->website ?? '')) : '';
        $websiteUrl = $websiteRaw !== ''
            ? (Str::startsWith($websiteRaw, ['http://', 'https://']) ? $websiteRaw : 'https://'.$websiteRaw)
            : null;

        return [
            'profileUser' => $profileUser,
            'canViewContent' => $canViewContent,
            'canViewLocation' => $canViewLocation,
            'location' => $location,
            'websiteUrl' => $websiteUrl,
            'websiteDisplay' => $websiteUrl
                ? Str::of($websiteUrl)->replaceStart('https://', '')->replaceStart('http://', '')->before('/')->toString()
                : null,
            'socialLinks' => $canViewContent && is_array($profileUser->social_links ?? null) ? $profileUser->social_links : [],
            'activityData' => $canViewContent ? Post::monthlyActivitySummaryForUser($profileUser) : [],
        ];
    }

    private function profileUser(): User
    {
        return User::query()
            ->select([
                'id',
                'name',
                'username',
                'bio',
                'location',
                'city',
                'website',
                'social_links',
                'created_at',
                'profile_visibility',
                'is_private',
                'is_banned',
                'scheduled_deletion_at',
                'deactivated_at',
                'suspended_until',
                'privacy_display_location',
                'posts_count',
                'pets_count',
                'photos_count',
            ])
            ->whereKey($this->profileUserId)
            ->firstOrFail();
    }

    private function viewer(): ?User
    {
        $viewer = auth()->user();

        return $viewer instanceof User ? $viewer : null;
    }
};
?>

@placeholder
<div data-ui="profile-tab-panel-loading" id="profile-panel-about" aria-busy="true">
 <x-ui.card>
 <div class="space-y-3">
 <div class="h-4 w-32 animate-pulse rounded-full bg-cream"></div>
 <div class="h-20 animate-pulse rounded-xl bg-cream"></div>
 <div class="h-20 animate-pulse rounded-xl bg-cream"></div>
 </div>
 </x-ui.card>
</div>
@endplaceholder

@php
 $data = $this->viewData();
@endphp

<div data-ui="profile-tab-panel" id="profile-panel-about" class="space-y-5">
 @if (! $data['canViewContent'])
 <x-ui.card>
 <x-ui.empty-state icon="🔒" title="About details are private"
 description="Follow {{ $data['profileUser']->name }} to view their profile details."/>
 </x-ui.card>
 @else
 <x-ui.card>
 <h2 class="text-base font-bold font-display text-bark">About {{ $data['profileUser']->name }}</h2>

 <div class="mt-4 space-y-4">
 @if ($data['profileUser']->bio)
 <p class="whitespace-pre-line text-sm leading-6 text-bark">{{ $data['profileUser']->bio }}</p>
 @else
 <p class="text-sm text-fur">No bio added yet.</p>
 @endif

 <dl class="grid gap-3 sm:grid-cols-3">
 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/60 p-3">
 <dt class="text-xs font-semibold uppercase text-fur">Posts</dt>
 <dd class="mt-1 font-display text-2xl font-bold text-bark">{{ number_format((int) ($data['profileUser']->posts_count ?? 0)) }}</dd>
 </div>
 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/60 p-3">
 <dt class="text-xs font-semibold uppercase text-fur">Pets</dt>
 <dd class="mt-1 font-display text-2xl font-bold text-bark">{{ number_format((int) ($data['profileUser']->pets_count ?? 0)) }}</dd>
 </div>
 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/60 p-3">
 <dt class="text-xs font-semibold uppercase text-fur">Photos</dt>
 <dd class="mt-1 font-display text-2xl font-bold text-bark">{{ number_format((int) ($data['profileUser']->photos_count ?? 0)) }}</dd>
 </div>
 </dl>
 </div>
 </x-ui.card>

 <x-ui.card>
 <h3 class="text-sm font-semibold text-bark">Details</h3>
 <div class="mt-3 space-y-2 text-sm text-fur">
 @if ($data['location'])
 <p>📍 Lives in {{ $data['location'] }}</p>
 @endif

 @if ($data['websiteUrl'])
 <p>
 🔗
 <a href="{{ $data['websiteUrl'] }}" target="_blank" rel="noopener noreferrer"
 class="inline-flex min-h-8 items-center rounded-[var(--radius-soft)] font-medium text-paw hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 {{ $data['websiteDisplay'] }}
 </a>
 </p>
 @endif

 @if ($data['socialLinks'] !== [])
 <div class="space-y-1">
 @foreach ($data['socialLinks'] as $label => $url)
 <p>
 🔗
 <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
 class="inline-flex min-h-8 items-center rounded-[var(--radius-soft)] font-medium text-paw hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 {{ Str::headline((string) $label) }}
 </a>
 </p>
 @endforeach
 </div>
 @endif

 <p>🗓️ Joined {{ optional($data['profileUser']->created_at)->format('F Y') }}</p>

 @if (! $data['location'] && ! $data['websiteUrl'] && $data['socialLinks'] === [])
 <p>No extra profile details added yet.</p>
 @endif
 </div>
 </x-ui.card>

 <x-ui.card>
 <h3 class="text-sm font-semibold text-bark">Recent activity</h3>
 <div class="mt-3">
 <x-ui.activity-chart :data="$data['activityData']"/>
 </div>
 </x-ui.card>
 @endif
</div>
