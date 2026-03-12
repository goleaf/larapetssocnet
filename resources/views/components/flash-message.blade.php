@props([
'type'=>'info',
'message'=> null,
'title'=> null,
'dismissible'=> true,
'timeout'=> 5000,
])

@if (filled($message ?? trim((string) $slot)))
 <x-ui.alert :type="$type" :title="$title" :dismissible="$dismissible" :timeout="$timeout" {{ $attributes }}>
 {{ $message ?? trim((string) $slot) }}
 </x-ui.alert>
@endif
