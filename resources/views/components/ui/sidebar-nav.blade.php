@props([
    'items' => [], /* array of ['label', 'href', 'icon' (optional), 'badge' (optional)] */
    'title' => null,
])

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if($title)
        <h4 class="px-3 mb-2 text-xs font-bold font-display uppercase tracking-wider text-fur">{{ $title }}</h4>
    @endif
    
    <nav class="space-y-1" aria-label="Sidebar">
        @foreach($items as $item)
            @php
                $isActive = request()->url() === url($item['href'] ?? '') || (isset($item['pattern']) && request()->is($item['pattern']));
                $label = $item['label'] ?? '';
                $href = $item['href'] ?? '#';
                $icon = $item['icon'] ?? null;
                $badge = $item['badge'] ?? null;
                $badgeVariant = $item['badgeVariant'] ?? ($isActive ? 'primary' : 'default');
            @endphp
            
            <a 
                href="{{ $href }}" 
                class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors {{ $isActive ? 'bg-paw-light text-paw-dark font-medium' : 'text-fur hover:bg-cream hover:text-bark' }}"
                @if($isActive) aria-current="page" @endif
            >
                <div class="flex items-center gap-3">
                    @if($icon)
                        <div class="shrink-0 {{ $isActive ? 'text-paw' : 'text-whisker' }}">
                            {!! $icon !!}
                        </div>
                    @endif
                    <span class="truncate">{{ $label }}</span>
                </div>
                
                @if($badge !== null)
                    <x-ui.badge :variant="$badgeVariant" size="sm" pill>
                        {{ $badge }}
                    </x-ui.badge>
                @endif
            </a>
        @endforeach
    </nav>
</div>
