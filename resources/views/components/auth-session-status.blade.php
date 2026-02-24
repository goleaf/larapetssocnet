@props(['status'])

@if ($status)
 <x-flash-message :message="$status" type="success" :timeout="0" {{ $attributes }} />
@endif
