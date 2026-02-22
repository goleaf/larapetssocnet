@props([
    'name' => 'q',
    'placeholder' => 'Search...',
    'value' => null,
    'action' => null,
])

<form
    @if (filled($action)) action="{{ $action }}" @endif
    method="GET"
    {{ $attributes->class(['relative']) }}
>
    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
        <svg class="h-4 w-4 text-whisker" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
        </svg>
    </div>
    <input
        type="search"
        name="{{ $name }}"
        value="{{ $value ?? request($name) }}"
        placeholder="{{ $placeholder }}"
        class="w-full bg-warm-white border border-whisker/50 rounded-md pl-9 pr-3.5 py-2.5 text-bark text-sm font-body placeholder:text-whisker transition-all duration-150 focus:outline-none focus:border-paw focus:shadow-input"
    >
</form>
