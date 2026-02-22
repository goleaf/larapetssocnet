@props([
    'name' => '',
    'avatar' => null,
    'subtitle' => null,
    'href' => null,
])

<div {{ $attributes->merge(['class' => 'flex items-center justify-between gap-3 p-3 rounded-lg hover:bg-cream transition-colors']) }}>
    <div class="flex items-center gap-3 min-w-0">
        @if($href)
            <a href="{{ $href }}" class="shrink-0">
                <x-ui.avatar :name="$name" :src="$avatar" size="md" />
            </a>
            <div class="min-w-0">
                <a href="{{ $href }}" class="block truncate text-sm font-semibold text-bark hover:text-paw transition-colors">{{ $name }}</a>
                @if($subtitle)
                    <p class="truncate text-xs text-fur">{{ $subtitle }}</p>
                @endif
            </div>
        @else
            <div class="shrink-0">
                <x-ui.avatar :name="$name" :src="$avatar" size="md" />
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-bark">{{ $name }}</p>
                @if($subtitle)
                    <p class="truncate text-xs text-fur">{{ $subtitle }}</p>
                @endif
            </div>
        @endif
    </div>
    
    @if(isset($action))
        <div class="shrink-0 flex items-center">
            {{ $action }}
        </div>
    @endif
</div>
