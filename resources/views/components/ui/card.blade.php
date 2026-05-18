@props([
'padding'=>'md',
'hover'=> false,
'as'=>'div',
])
<{{ $as }} {{ $attributes->except('class')->merge(['class'=> \Illuminate\Support\Arr::toCssClasses([
'shell-card',
 $hover ?'ui-card-interactive cursor-pointer':'',
 $attributes->get('class'),
])]) }}>
 <div class="{{ ['none' => '', '0' => '', 'sm' => 'p-4', 'md' => 'p-6', 'base' => 'p-6', 'lg' => 'p-8'][(string) $padding] ?? 'p-6' }}">
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
