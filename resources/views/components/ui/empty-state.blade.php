@props([
    'icon' => '🐾',
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'w-full py-16 px-4 flex flex-col items-center justify-center text-center']) }}>
    <div class="text-5xl mb-4 opacity-80">{{ $icon }}</div>
    
    <h3 class="text-lg font-semibold font-display text-bark">{{ $title }}</h3>
    
    @if($description)
        <p class="text-sm text-fur mt-1 max-w-xs mx-auto">{{ $description }}</p>
    @endif
    
    @if(isset($action))
        <div class="mt-6">
            {{ $action }}
        </div>
    @endif
</div>
