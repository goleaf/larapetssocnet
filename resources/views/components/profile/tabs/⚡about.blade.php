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
     *     displayName: string,
     *     canViewContent: bool,
     *     overviewItems: list<array{label: string, value: string}>,
     *     detailItems: list<array{label: string, value: string, datetime?: string|null}>,
     *     contactItems: list<array{label: string, url: string, display: string}>,
     *     interests: list<string>,
     *     activityData: list<array{month: string, count: int}>
     * }
     */
    public function viewData(): array
    {
        $profileUser = $this->profileUser();
        $viewer = $this->viewer();
        $visibility = app(ProfileVisibilityService::class);
        $canViewContent = $visibility->canViewFullProfile($viewer, $profileUser);
        $displayName = (string) ($profileUser->display_name ?: $profileUser->name);

        if (! $canViewContent) {
            return [
                'profileUser' => $profileUser,
                'displayName' => $displayName,
                'canViewContent' => false,
                'overviewItems' => [],
                'detailItems' => [],
                'contactItems' => [],
                'interests' => [],
                'activityData' => [],
            ];
        }

        $canViewSensitiveProfileFields = $this->canViewSensitiveProfileFields($viewer, $profileUser);
        $location = $visibility->canViewLocation($viewer, $profileUser)
            ? $this->filledString($profileUser->location) ?? $this->filledString($profileUser->city)
            : null;
        $birthDate = ($canViewSensitiveProfileFields || (bool) $profileUser->privacy_display_birthdate)
            ? $profileUser->birth_date?->format('F j, Y')
            : null;
        $lastSeen = ($canViewSensitiveProfileFields || (bool) $profileUser->privacy_display_last_seen)
            ? $profileUser->last_seen_at
            : null;
        $email = ($canViewSensitiveProfileFields || (bool) $profileUser->privacy_display_email)
            ? $this->filledString($profileUser->email)
            : null;
        $website = $this->externalLink($profileUser->website);

        return [
            'profileUser' => $profileUser,
            'displayName' => $displayName,
            'canViewContent' => true,
            'overviewItems' => $this->overviewItems($profileUser, $displayName),
            'detailItems' => $this->detailItems($profileUser, $location, $birthDate, $lastSeen),
            'contactItems' => $this->contactItems($website, $email, $profileUser->social_links),
            'interests' => $this->interests($profileUser->interests_text),
            'activityData' => Post::monthlyActivitySummaryForUser($profileUser),
        ];
    }

    private function profileUser(): User
    {
        return User::query()
            ->select([
                'id',
                'name',
                'display_name',
                'username',
                'email',
                'bio',
                'headline',
                'pronouns',
                'location',
                'city',
                'website',
                'social_links',
                'interests_text',
                'created_at',
                'birth_date',
                'last_seen_at',
                'profile_visibility',
                'is_private',
                'is_banned',
                'scheduled_deletion_at',
                'deactivated_at',
                'suspended_until',
                'privacy_display_email',
                'privacy_display_location',
                'privacy_display_birthdate',
                'privacy_display_last_seen',
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

    private function canViewSensitiveProfileFields(?User $viewer, User $profileUser): bool
    {
        return $viewer instanceof User
            && ($viewer->is($profileUser) || $viewer->hasAnyRole(['admin', 'moderator']));
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function overviewItems(User $profileUser, string $displayName): array
    {
        return collect([
            ['label' => 'Display name', 'value' => $displayName],
            ['label' => 'Username', 'value' => '@'.$profileUser->username],
            ['label' => 'Headline', 'value' => $this->filledString($profileUser->headline)],
            ['label' => 'Pronouns', 'value' => $this->filledString($profileUser->pronouns)],
        ])
            ->filter(fn (array $item): bool => filled($item['value']))
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, value: string, datetime?: string|null}>
     */
    private function detailItems(User $profileUser, ?string $location, ?string $birthDate, mixed $lastSeen): array
    {
        return collect([
            [
                'label' => 'Location',
                'value' => $location,
            ],
            [
                'label' => 'Birthday',
                'value' => $birthDate,
                'datetime' => $profileUser->birth_date?->toDateString(),
            ],
            [
                'label' => 'Joined',
                'value' => $profileUser->created_at?->format('F Y'),
                'datetime' => $profileUser->created_at?->toIso8601String(),
            ],
            [
                'label' => 'Last active',
                'value' => is_object($lastSeen) && method_exists($lastSeen, 'diffForHumans') ? $lastSeen->diffForHumans() : null,
                'datetime' => is_object($lastSeen) && method_exists($lastSeen, 'toIso8601String') ? $lastSeen->toIso8601String() : null,
            ],
        ])
            ->filter(fn (array $item): bool => filled($item['value']))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $socialLinks
     * @return list<array{label: string, url: string, display: string}>
     */
    private function contactItems(?array $website, ?string $email, ?array $socialLinks): array
    {
        $items = collect();

        if (is_array($website)) {
            $items->push([
                'label' => 'Website',
                'url' => $website['url'],
                'display' => $website['display'],
            ]);
        }

        foreach ($socialLinks ?? [] as $label => $url) {
            $link = $this->externalLink($url);

            if (! is_array($link)) {
                continue;
            }

            $items->push([
                'label' => Str::headline((string) $label),
                'url' => $link['url'],
                'display' => $link['display'],
            ]);
        }

        if ($email !== null) {
            $items->push([
                'label' => 'Email',
                'url' => 'mailto:'.$email,
                'display' => $email,
            ]);
        }

        return $items->values()->all();
    }

    /**
     * @return array{url: string, display: string}|null
     */
    private function externalLink(mixed $value): ?array
    {
        $rawValue = $this->filledString($value);

        if ($rawValue === null) {
            return null;
        }

        $url = Str::startsWith($rawValue, ['http://', 'https://']) ? $rawValue : 'https://'.$rawValue;

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return [
            'url' => $url,
            'display' => Str::of($url)->replaceStart('https://', '')->replaceStart('http://', '')->trim('/')->toString(),
        ];
    }

    /**
     * @return list<string>
     */
    private function interests(mixed $value): array
    {
        $interests = $this->filledString($value);

        if ($interests === null) {
            return [];
        }

        return collect(preg_split('/[,;\n]+/', $interests) ?: [])
            ->map(fn (string $interest): string => trim($interest))
            ->filter()
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    private function filledString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
};
?>

@placeholder
<div data-ui="profile-tab-panel-loading" id="profile-panel-about" aria-busy="true">
 <x-ui.card>
 <div class="space-y-4">
 <div class="h-4 w-36 animate-pulse rounded-full bg-cream"></div>
 <div class="grid gap-3 sm:grid-cols-2">
 <div class="h-24 animate-pulse rounded-[var(--radius-soft)] bg-cream"></div>
 <div class="h-24 animate-pulse rounded-[var(--radius-soft)] bg-cream"></div>
 </div>
 <div class="h-28 animate-pulse rounded-[var(--radius-soft)] bg-cream"></div>
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
 <div class="flex flex-col gap-5">
 <div>
 <p class="text-xs font-semibold uppercase tracking-wide text-fur">Overview</p>
 <h2 class="mt-1 text-xl font-bold font-display text-bark">About {{ $data['displayName'] }}</h2>
 </div>

 @if ($data['overviewItems'] !== [])
 <dl data-ui="profile-about-overview" class="grid gap-3 sm:grid-cols-2">
 @foreach ($data['overviewItems'] as $item)
 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/50 p-3">
 <dt class="text-xs font-semibold uppercase tracking-wide text-fur">{{ $item['label'] }}</dt>
 <dd class="mt-1 break-words text-sm font-semibold text-bark">{{ $item['value'] }}</dd>
 </div>
 @endforeach
 </dl>
 @endif

 <div data-ui="profile-about-bio" class="rounded-[var(--radius-soft)] border border-whisker/30 bg-warm-white p-4">
 <h3 class="text-sm font-semibold text-bark">Bio</h3>
 @if ($data['profileUser']->bio)
 <p class="mt-2 whitespace-pre-line text-sm leading-6 text-bark">{{ $data['profileUser']->bio }}</p>
 @else
 <p class="mt-2 text-sm text-fur">No bio added yet.</p>
 @endif
 </div>

 @if ($data['interests'] !== [])
 <div data-ui="profile-about-interests" class="space-y-2">
 <h3 class="text-sm font-semibold text-bark">Interests</h3>
 <ul class="flex flex-wrap gap-2" role="list" aria-label="Profile interests">
 @foreach ($data['interests'] as $interest)
 <li>
 <x-ui.badge variant="neutral" size="md">{{ $interest }}</x-ui.badge>
 </li>
 @endforeach
 </ul>
 </div>
 @endif
 </div>
 </x-ui.card>

 <x-ui.card>
 <div class="flex flex-col gap-4">
 <div>
 <p class="text-xs font-semibold uppercase tracking-wide text-fur">Details</p>
 <h3 class="mt-1 text-lg font-bold font-display text-bark">Public profile details</h3>
 </div>

 @if ($data['detailItems'] !== [])
 <dl data-ui="profile-about-details" class="grid gap-3 sm:grid-cols-2">
 @foreach ($data['detailItems'] as $item)
 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/50 p-3">
 <dt class="text-xs font-semibold uppercase tracking-wide text-fur">{{ $item['label'] }}</dt>
 <dd class="mt-1 break-words text-sm font-semibold text-bark">
 @if (isset($item['datetime']) && $item['datetime'])
 <time datetime="{{ $item['datetime'] }}">{{ $item['value'] }}</time>
 @else
 {{ $item['value'] }}
 @endif
 </dd>
 </div>
 @endforeach
 </dl>
 @else
 <p class="text-sm text-fur">No public details added yet.</p>
 @endif
 </div>
 </x-ui.card>

 <x-ui.card>
 <div class="flex flex-col gap-4">
 <div>
 <p class="text-xs font-semibold uppercase tracking-wide text-fur">Links</p>
 <h3 class="mt-1 text-lg font-bold font-display text-bark">Public links and contact</h3>
 </div>

 @if ($data['contactItems'] !== [])
 <ul data-ui="profile-about-contact" class="grid gap-3 sm:grid-cols-2" role="list" aria-label="Public profile links">
 @foreach ($data['contactItems'] as $item)
 <li class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/50 p-3">
 <p class="text-xs font-semibold uppercase tracking-wide text-fur">{{ $item['label'] }}</p>
 <a href="{{ $item['url'] }}" @if (! Str::startsWith($item['url'], 'mailto:')) target="_blank" rel="noopener noreferrer" @endif
 class="mt-1 inline-flex min-h-8 max-w-full items-center break-all rounded-[var(--radius-soft)] text-sm font-semibold text-paw transition-colors hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 {{ $item['display'] }}
 </a>
 </li>
 @endforeach
 </ul>
 @else
 <p class="text-sm text-fur">No public links added yet.</p>
 @endif
 </div>
 </x-ui.card>

 <x-ui.card>
 <div class="flex flex-col gap-4">
 <div>
 <p class="text-xs font-semibold uppercase tracking-wide text-fur">Activity</p>
 <h3 class="mt-1 text-lg font-bold font-display text-bark">Community activity</h3>
 </div>

 <dl data-ui="profile-about-activity-stats" class="grid gap-3 sm:grid-cols-3">
 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/60 p-3">
 <dt class="text-xs font-semibold uppercase tracking-wide text-fur">Posts</dt>
 <dd class="mt-1 font-display text-2xl font-bold text-bark">{{ number_format((int) ($data['profileUser']->posts_count ?? 0)) }}</dd>
 </div>
 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/60 p-3">
 <dt class="text-xs font-semibold uppercase tracking-wide text-fur">Pets</dt>
 <dd class="mt-1 font-display text-2xl font-bold text-bark">{{ number_format((int) ($data['profileUser']->pets_count ?? 0)) }}</dd>
 </div>
 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/60 p-3">
 <dt class="text-xs font-semibold uppercase tracking-wide text-fur">Photos</dt>
 <dd class="mt-1 font-display text-2xl font-bold text-bark">{{ number_format((int) ($data['profileUser']->photos_count ?? 0)) }}</dd>
 </div>
 </dl>

 <div data-ui="profile-about-activity-chart">
 <x-ui.activity-chart :data="$data['activityData']"/>
 </div>
 </div>
 </x-ui.card>
 @endif
</div>
