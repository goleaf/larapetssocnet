@props([
    'group',
    'owner' => null,
    'membership' => null,
])

@php
    $groupRouteKey = filled((string) ($group->slug ?? '')) ? $group->slug : $group->id;
    $privacyValue = strtolower((string) ($group->privacy ?? (($group->is_private ?? false) ? 'private' : 'public')));
    $privacyLabel = \Illuminate\Support\Str::headline($privacyValue);
    $speciesValue = (string) (data_get($group, 'species') ?: data_get($group, 'species_focus', 'all_pets'));
    $speciesLabel = \Illuminate\Support\Str::headline(str_replace(['-', '_'], ' ', $speciesValue));
    $groupHref = route('groups.show', $groupRouteKey);

    $rawMembershipStatus = data_get($membership, 'status', '');
    $membershipStatusValue = $rawMembershipStatus instanceof \BackedEnum
        ? $rawMembershipStatus->value
        : $rawMembershipStatus;
    $membershipStatus = strtolower((string) $membershipStatusValue);
    $isMember = $membership && in_array($membershipStatus, ['', 'active', 'accepted'], true);
    $isPending = $membership && $membershipStatus === 'pending';

    $coverUrl = (string) (data_get($group, 'cover_photo_url') ?: data_get($group, 'cover_image_path'));
    $avatarUrl = (string) (data_get($group, 'avatar_url') ?: data_get($group, 'profile_photo_url'));
    $descriptionText = strip_tags((string) (data_get($group, 'description_html') ?: data_get($group, 'description')));

    $privacyTone = match ($privacyValue) {
        'private' => 'warning',
        'secret' => 'danger',
        default => 'success',
    };
@endphp

<article
    class="shell-card ui-card-interactive group overflow-hidden p-0"
    data-ui="group-card"
    aria-label="{{ __('Group: :name', ['name' => $group->name]) }}"
>
    <a href="{{ $groupHref }}" class="block focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-paw" aria-label="{{ __('Open group: :name', ['name' => $group->name]) }}">
        <div class="relative h-28 w-full bg-[color:var(--ui-surface-muted)]">
            @if ($coverUrl !== '')
                <img src="{{ $coverUrl }}" alt="{{ $group->name }} cover" class="h-full w-full object-cover" loading="lazy">
            @else
                <div class="h-full w-full ui-gradient-soft"></div>
            @endif

            <div class="absolute bottom-3 left-3">
                <x-ui.badge :tone="$privacyTone" size="sm">{{ $privacyLabel }}</x-ui.badge>
            </div>
        </div>
    </a>

    <div class="space-y-4 p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="truncate text-base font-semibold ui-text">
                    <a href="{{ $groupHref }}" class="hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">{{ $group->name }}</a>
                </h3>
                <p class="mt-0.5 text-xs shell-text-muted">{{ $speciesLabel }}</p>
            </div>
        </div>

        @if (filled((string) $descriptionText))
            <p class="line-clamp-3 text-sm leading-6 shell-text-muted">{{ \Illuminate\Support\Str::limit($descriptionText, 150) }}</p>
        @endif

        <div class="grid grid-cols-2 gap-2 text-xs shell-text-muted">
            <span class="ui-list-item px-3 py-2">
                <span class="block font-semibold ui-text">{{ number_format((int) ($group->members_count ?? 0)) }}</span>
                members
            </span>
            <span class="ui-list-item px-3 py-2">
                <span class="block font-semibold ui-text">{{ number_format((int) ($group->posts_count ?? 0)) }}</span>
                posts
            </span>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            @if ($owner)
                <a href="{{ route('profile.show', ['user' => $owner]) }}" class="flex min-w-0 items-center gap-2 rounded-[var(--radius-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                    <x-avatar :src="$owner->avatar_url" :name="$owner->name" size="xs"/>
                    <span class="truncate text-xs shell-text-muted">{{ $owner->name }}</span>
                </a>
            @else
                <span class="text-xs shell-text-muted">Community group</span>
            @endif

            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button :href="$groupHref" variant="ghost" size="sm" class="min-h-11">View</x-ui.button>

                @auth
                    @if ($isMember)
                        <x-ui.badge variant="primary" size="sm">Member</x-ui.badge>
                    @elseif ($isPending)
                        <x-ui.badge variant="warning" size="sm">Pending</x-ui.badge>
                    @elseif ($privacyValue !== 'secret')
                        <form method="POST" action="{{ route('groups.join', $groupRouteKey) }}" class="inline-block">
                            @csrf
                            <x-ui.button type="submit" variant="primary" size="sm" class="min-h-11">
                                {{ $privacyValue === 'public' ? 'Join' : 'Request' }}
                            </x-ui.button>
                        </form>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</article>
