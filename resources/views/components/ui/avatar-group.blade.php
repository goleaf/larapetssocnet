@props([
    'users' => [],
    'max' => 5,
    'size' => 'md',
    'total' => null,
])

@php
    $collection = collect($users);
    $displayUsers = $collection->take($max);
    $remaining = ($total ?? $collection->count()) - $displayUsers->count();

    $sizeClasses = [
        'sm' => 'h-8 w-8 text-xs',
        'md' => 'h-10 w-10 text-sm',
    ][$size] ?? 'h-10 w-10 text-sm';

    $overlapClass = $size === 'sm' ? '-ml-2' : '-ml-3';
@endphp

<div {{ $attributes->class(['flex items-center']) }}>
    @foreach ($displayUsers as $index => $user)
        <div @class([$overlapClass => $index > 0, 'relative'])>
            <x-avatar
                :src="$user->avatar_url ?? $user->avatar_path ?? null"
                :name="$user->name ?? 'User'"
                :size="$size"
                class="ring-2 ring-warm-white"
            />
        </div>
    @endforeach

    @if ($remaining > 0)
        <div class="{{ $overlapClass }}">
            <span class="inline-flex items-center justify-center rounded-full bg-cream border-2 border-warm-white text-fur font-semibold {{ $sizeClasses }}">
                +{{ $remaining > 99 ? '99' : $remaining }}
            </span>
        </div>
    @endif
</div>
