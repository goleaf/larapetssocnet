@props(['messages' => []])

@php
 $message = collect((array) $messages)->filter()->first();
@endphp

@if ($message)
 <x-ui.hint :error="$message" {{ $attributes }}/>
@endif
