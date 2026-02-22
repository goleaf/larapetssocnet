@props([
    'user' => null,
    'role' => null,
    'subtitle' => null,
])

@php
    $userName = $user?->name ?? 'Unknown User';
    $userUsername = $user?->username ?? null;
    $avatarSrc = $user?->avatar_url ?? $user?->avatar_path ?? null;
    $profileUrl = $userUsername ? route('profile.show', $userUsername) : null;
@endphp

<div {{ $attributes->class(['flex items-center justify-between gap-3 py-2']) }}>
    <div class="flex items-center gap-3 min-w-0">
        <a @if ($profileUrl) href="{{ $profileUrl }}" @endif class="shrink-0">
            <x-avatar :src="$avatarSrc" :name="$userName" size="md" />
        </a>
        <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <a @if ($profileUrl) href="{{ $profileUrl }}" @endif class="text-sm font-semibold text-bark truncate hover:text-paw transition-colors">
                    {{ $userName }}
                </a>
                @if (filled($role))
                    <x-ui.role-badge :role="$role" />
                @endif
            </div>
            @if (filled($userUsername))
                <p class="text-xs text-fur truncate">{{ '@' . $userUsername }}</p>
            @endif
            @if (filled($subtitle))
                <p class="text-xs text-fur mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @if (isset($action))
        <div class="shrink-0 flex items-center gap-2">
            {{ $action }}
        </div>
    @endif
</div>
