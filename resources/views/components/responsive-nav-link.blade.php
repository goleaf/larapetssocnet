@props(['active'])

@php
 $classes = ($active ?? false)
 ?'shell-nav-link active w-full justify-start'
 :'shell-nav-link w-full justify-start';
@endphp

<a {{ $attributes->merge(['class'=> $classes]) }}>
 {{ $slot }}
</a>
