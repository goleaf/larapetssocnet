@props([
'type'=>'info',
'message'=> null,
'title'=> null,
'dismissible'=> true,
'timeout'=> 5000,
])

@php
 $content = $message ?? trim((string) $slot);
@endphp

@if (filled($content))
 <x-ui.alert :type="$type" :title="$title" :dismissible="$dismissible" :timeout="$timeout" {{ $attributes }}>
 {{ $content }}
 </x-ui.alert>
@endif
