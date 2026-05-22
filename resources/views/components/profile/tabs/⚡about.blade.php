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
     *     bioDetails: list<array{label: string, value: string, icon: string, iconPath: string, url?: string, datetime?: string|null}>,
     *     overviewItems: list<array{label: string, value: string}>,
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
                'bioDetails' => [],
                'overviewItems' => [],
                'contactItems' => [],
                'interests' => [],
                'activityData' => [],
            ];
        }

        $location = $visibility->canViewLocation($viewer, $profileUser)
            ? $this->filledString($profileUser->location) ?? $this->filledString($profileUser->city)
            : null;
        $website = $this->externalLink($profileUser->website);

        return [
            'profileUser' => $profileUser,
            'displayName' => $displayName,
            'canViewContent' => true,
            'bioDetails' => $this->bioDetails($profileUser, $location, $website),
            'overviewItems' => $this->overviewItems($profileUser, $displayName),
            'contactItems' => $this->contactItems($profileUser->social_links),
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
                'profile_visibility',
                'is_private',
                'is_banned',
                'scheduled_deletion_at',
                'deactivated_at',
                'suspended_until',
                'privacy_display_location',
                'privacy_display_birthdate',
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

    /**
     * @param  array{url: string, display: string}|null  $website
     * @return list<array{label: string, value: string, icon: string, iconPath: string, url?: string, datetime?: string|null}>
     */
    private function bioDetails(User $profileUser, ?string $location, ?array $website): array
    {
        $items = collect([
            [
                'label' => 'Member since',
                'value' => 'Member since '.$profileUser->created_at?->format('F Y').'.',
                'icon' => 'calendar',
                'datetime' => $profileUser->created_at?->toIso8601String(),
            ],
        ]);

        if ($location !== null) {
            $items->push([
                'label' => 'Location',
                'value' => $location,
                'icon' => 'map-pin',
            ]);
        }

        if (is_array($website)) {
            $items->push([
                'label' => 'Website',
                'value' => $website['display'],
                'url' => $website['url'],
                'icon' => 'external-link',
            ]);
        }

        if ((bool) $profileUser->privacy_display_birthdate && $profileUser->birth_date !== null) {
            $items->push([
                'label' => 'Age',
                'value' => 'Age '.$profileUser->birth_date->age,
                'icon' => 'cake',
            ]);
        }

        return $items
            ->filter(fn (array $item): bool => filled($item['value']))
            ->map(fn (array $item): array => [
                ...$item,
                'iconPath' => $this->metadataIcon((string) $item['icon']),
            ])
            ->values()
            ->all();
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
     * @return list<array{label: string, url: string, display: string}>
     */
    private function contactItems(?array $socialLinks): array
    {
        $items = collect();

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

        return $items->values()->all();
    }

    private function metadataIcon(string $icon): string
    {
        return match ($icon) {
            'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
            'map-pin' => '<path d="M20 10c0 4.99-5.54 10.19-7.4 11.8a1 1 0 0 1-1.2 0C9.54 20.19 4 14.99 4 10a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
            'external-link' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
            'cake' => '<path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M4 16s.5-1 2-1 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2-1 2-1"/><path d="M2 21h20"/><path d="M7 8v3"/><path d="M12 8v3"/><path d="M17 8v3"/><path d="M7 4h.01"/><path d="M12 4h.01"/><path d="M17 4h.01"/>',
            default => '',
        };
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
	 <section data-ui="profile-about-bio" class="flex flex-col gap-5" aria-labelledby="profile-about-bio-heading">
	 <div>
	 <p class="text-xs font-semibold uppercase tracking-wide text-fur">Bio</p>
	 <h2 id="profile-about-bio-heading" class="mt-1 text-xl font-bold font-display text-bark">About {{ $data['displayName'] }}</h2>
	 </div>

	 @if ($data['profileUser']->bio)
	 <p class="whitespace-pre-line text-sm leading-6 text-bark">{{ $data['profileUser']->bio }}</p>
	 @else
	 <p class="text-sm text-fur">No bio added yet.</p>
	 @endif

	 <ul data-ui="profile-about-bio-details" class="flex flex-col gap-3 border-t border-whisker/30 pt-4 text-sm text-bark" role="list" aria-label="Profile biography details">
	 @foreach ($data['bioDetails'] as $item)
	 <li class="flex items-start gap-3">
	 <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[var(--radius-soft)] bg-cream text-fur" aria-hidden="true">
	 <svg data-icon="{{ $item['icon'] }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">{!! $item['iconPath'] !!}</svg>
	 </span>
	 <span class="min-w-0 flex-1 leading-7">
	 @if (isset($item['url']))
	 <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer"
	 class="inline-flex min-h-8 max-w-full items-center break-all rounded-[var(--radius-soft)] font-semibold text-paw transition-colors hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
	 {{ $item['value'] }}
	 </a>
	 @elseif (isset($item['datetime']) && $item['datetime'])
	 <time datetime="{{ $item['datetime'] }}">{{ $item['value'] }}</time>
	 @else
	 {{ $item['value'] }}
	 @endif
	 </span>
	 </li>
	 @endforeach
	 </ul>
	 </section>
	 </x-ui.card>

	 @if ($data['overviewItems'] !== [])
	 <x-ui.card>
	 <div class="flex flex-col gap-4">
	 <div>
	 <p class="text-xs font-semibold uppercase tracking-wide text-fur">Basics</p>
	 <h3 class="mt-1 text-lg font-bold font-display text-bark">Profile basics</h3>
	 </div>

	 <dl data-ui="profile-about-overview" class="grid gap-3 sm:grid-cols-2">
	 @foreach ($data['overviewItems'] as $item)
	 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/50 p-3">
	 <dt class="text-xs font-semibold uppercase tracking-wide text-fur">{{ $item['label'] }}</dt>
	 <dd class="mt-1 break-words text-sm font-semibold text-bark">
	 {{ $item['value'] }}
	 </dd>
	 </div>
	 @endforeach
	 </dl>
	 </div>
	 </x-ui.card>
	 @endif

	 @if ($data['interests'] !== [])
	 <x-ui.card>
	 <div data-ui="profile-about-interests" class="space-y-3">
	 <div>
	 <p class="text-xs font-semibold uppercase tracking-wide text-fur">Interests</p>
	 <h3 class="mt-1 text-lg font-bold font-display text-bark">What they care about</h3>
	 </div>
	 <ul class="flex flex-wrap gap-2" role="list" aria-label="Profile interests">
	 @foreach ($data['interests'] as $interest)
	 <li>
	 <x-ui.badge variant="neutral" size="md">{{ $interest }}</x-ui.badge>
	 </li>
	 @endforeach
	 </ul>
	 </div>
	 </x-ui.card>
	 @endif

	 @if ($data['contactItems'] !== [])
	 <x-ui.card>
	 <div class="flex flex-col gap-4">
	 <div>
	 <p class="text-xs font-semibold uppercase tracking-wide text-fur">Links</p>
	 <h3 class="mt-1 text-lg font-bold font-display text-bark">Social links</h3>
	 </div>

	 <ul data-ui="profile-about-contact" class="grid gap-3 sm:grid-cols-2" role="list" aria-label="Public profile links">
	 @foreach ($data['contactItems'] as $item)
	 <li class="rounded-[var(--radius-soft)] border border-whisker/30 bg-cream/50 p-3">
	 <p class="text-xs font-semibold uppercase tracking-wide text-fur">{{ $item['label'] }}</p>
	 <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer"
	 class="mt-1 inline-flex min-h-8 max-w-full items-center break-all rounded-[var(--radius-soft)] text-sm font-semibold text-paw transition-colors hover:text-paw-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
	 {{ $item['display'] }}
	 </a>
	 </li>
	 @endforeach
	 </ul>
	 </div>
	 </x-ui.card>
	 @endif

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
