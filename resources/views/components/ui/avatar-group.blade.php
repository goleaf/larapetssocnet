@props([
    'users' => [],
    'max' => 5,
    'size' => 'md',
    'total' => null,
])

@php
    $items = is_iterable($users) ? collect($users) : collect([]);
    $displayUsers = $items->take($max);
    $actualTotal = $total ?? $items->count();
    $remaining = max(0, $actualTotal - $displayUsers->count());
    
    $margins = [
        'sm' => '-ml-2',
        'md' => '-ml-3',
    ];
    
    $marginClass = $margins[$size] ?? $margins['md'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center isolate']) }}>
    @foreach($displayUsers as $index => $user)
        @php
            $name = is_object($user) ? current((array) $user) : (is_array($user) ? ($user['name'] ?? 'User') : 'User'); // Simple fallback
            if (is_object($user) && method_exists($user, 'avatarUrl')) {
                $src = $user->avatarUrl();
            } elseif (is_object($user) && property_exists($user, 'avatar_url')) {
                $src = $user->avatar_url;
            } elseif (is_array($user)) {
                $src = $user['avatar_url'] ?? $user['src'] ?? null;
            } else {
                $src = null;
            }
            if (is_object($user) && property_exists($user, 'name')) {
                $name = $user->name;
            }
        @endphp
        
        <x-ui.avatar 
            :src="$src" 
            :name="$name" 
            :size="$size" 
            class="{{ $index > 0 ? $marginClass : '' }} ring-2 ring-warm-white relative z-[{{ compact('index') ? 10 - $index : 0 }}]" 
            style="z-index: {{ 10 - $index }}"
        />
    @endforeach
    
    @if($remaining > 0)
        @php
            $sizes = [
                'sm' => 'w-8 h-8 text-xs',
                'md' => 'w-10 h-10 text-sm',
            ];
            $sizeClass = $sizes[$size] ?? $sizes['md'];
        @endphp
        <div class="{{ $sizeClass }} {{ $marginClass }} relative z-0 flex items-center justify-center rounded-pill bg-cream border border-whisker/30 text-fur font-medium ring-2 ring-warm-white shrink-0">
            +{{ $remaining }}
        </div>
    @endif
</div>
