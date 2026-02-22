@props([
    'tabs' => [], /* array of ['label', 'value', 'count' => null] */
    'active' => null,
    'paramName' => 'tab',
])

@php
    $activeTab = $active ?? request()->query($paramName) ?? ($tabs[0]['value'] ?? ($tabs[0] ?? null));
@endphp

<div {{ $attributes->merge(['class' => 'border-b border-whisker/40 w-full mb-6']) }}>
    <nav class="-mb-px flex space-x-8 overflow-x-auto no-scrollbar" aria-label="Tabs">
        @foreach($tabs as $tab)
            @php
                $val = is_array($tab) ? ($tab['value'] ?? '') : $tab;
                $label = is_array($tab) ? ($tab['label'] ?? $val) : $val;
                $count = is_array($tab) ? ($tab['count'] ?? null) : null;
                $isActive = $activeTab === $val;
                
                $url = request()->fullUrlWithQuery([$paramName => $val]);
            @endphp
            
            <a 
                href="{{ $url }}"
                class="whitespace-nowrap flex items-center gap-2 py-4 px-1 border-b-2 text-sm transition-colors {{ $isActive ? 'border-paw text-paw font-semibold' : 'border-transparent text-fur hover:text-bark hover:border-whisker' }}"
                @if($isActive) aria-current="page" @endif
            >
                {{ $label }}
                
                @if($count !== null)
                    <x-ui.badge :variant="$isActive ? 'primary' : 'default'" size="sm" pill class="ml-1.5">
                        {{ $count }}
                    </x-ui.badge>
                @endif
            </a>
        @endforeach
    </nav>
</div>
