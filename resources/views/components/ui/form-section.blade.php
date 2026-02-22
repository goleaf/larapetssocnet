@props([
    'title' => null,
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col md:flex-row gap-6 mb-8']) }}>
    @if($title || $description)
        <div class="w-full md:w-1/3 shrink-0">
            @if($title)
                <h3 class="text-base font-semibold font-display text-bark mb-1">{{ $title }}</h3>
            @endif
            
            @if($description)
                <p class="text-sm text-fur">{{ $description }}</p>
            @endif
        </div>
    @endif
    
    <div class="w-full md:w-2/3 md:max-w-xl space-y-5">
        {{ $slot }}
    </div>
</div>
