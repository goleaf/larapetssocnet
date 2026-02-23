@props(['messages'])

@if ($messages)
 <ul {{ $attributes->merge(['class'=>'space-y-1 text-sm font-medium']) }} style="color: var(--ui-danger);">
 @foreach ((array) $messages as $message)
 <li>{{ $message }}</li>
 @endforeach
 </ul>
@endif
