@props(['messages' => null])

@php
    $messages = collect((array) $messages)
        ->filter(fn ($message): bool => filled($message))
        ->values();
@endphp

@if ($messages->isNotEmpty())
    <ul {{ $attributes->merge(['class' => 'mt-2 space-y-1 text-sm text-rose']) }}>
        @foreach ($messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
