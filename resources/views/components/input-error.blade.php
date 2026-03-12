@props(['messages' => []])

@if (collect((array) $messages)->filter()->first())
 <x-ui.hint :error="collect((array) $messages)->filter()->first()" {{ $attributes }}/>
@endif
