@props([
    'name' => 'query',
    'placeholder' => 'Search...',
    'value' => null,
    'action' => '',
])

<form action="{{ $action }}" method="GET" {{ $attributes->except(['class']) }} class="relative lg:w-64 max-w-full w-full {{ $attributes->get('class') }}" x-data="{ query: '{{ str_replace('\'', '\\\'', $value ?? request()->query($name)) }}' }">
    <div class="absolute left-3 top-1/2 -translate-y-1/2 text-fur pointer-events-none">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
        </svg>
    </div>
    
    <input 
        type="search" 
        name="{{ $name }}" 
        placeholder="{{ $placeholder }}"
        x-model="query"
        class="w-full bg-cream border border-whisker rounded-md pl-10 pr-3 py-2 text-sm text-bark placeholder:text-whisker focus:outline-none focus:border-paw focus:shadow-input transition-all duration-150 focus:bg-warm-white"
    >
</form>
