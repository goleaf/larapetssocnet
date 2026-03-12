@props([
'padding'=>'md',
'hover'=> false,
'as'=>'div',
])
<{{ $as }} {{ $attributes->except('class')->merge(['class'=> \Illuminate\Support\Arr::toCssClasses([
'shell-card',
 $hover ?'cursor-pointer transition-all duration-150 hover:-translate-y-0.5 hover:shadow-card-hover':'',
 $attributes->get('class'),
])]) }}>
 <div class="{{ ['none' => '', '0' => '', 'sm' => 'p-3', 'md' => 'p-5', 'lg' => 'p-7'][(string) $padding] ?? 'p-5' }}">
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
