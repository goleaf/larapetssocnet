@props([
'padding'=>'md',
'hover'=> false,
'as'=>'div',
])

@php
 $paddings = [
'none'=>'',
'0'=>'',
'sm'=>'p-3',
'md'=>'p-5',
'lg'=>'p-7',
 ];

 $paddingKey = (string) $padding;
 $paddingClass = $paddings[$paddingKey] ?? $paddings['md'];

 $classes = \Illuminate\Support\Arr::toCssClasses([
'shell-card',
 $hover ?'cursor-pointer transition-all duration-150 hover:-translate-y-0.5 hover:shadow-card-hover':'',
 $attributes->get('class'),
 ]);
@endphp

<{{ $as }} {{ $attributes->except('class')->merge(['class'=> $classes]) }}>
 <div class="{{ $paddingClass }}">
 @isset($header)
 {{ $header }}
 @endisset

 {{ $slot }}

 @isset($footer)
 <div class="mt-4 border-t border-whisker/40 pt-4">
 {{ $footer }}
 </div>
 @endisset
 </div>
</{{ $as }}>
