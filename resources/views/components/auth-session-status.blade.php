@props(['status'])

@if ($status)
 <x-ui.alert type="success" {{ $attributes }}>{{ $status }}</x-ui.alert>
@endif
