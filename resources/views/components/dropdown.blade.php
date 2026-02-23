@props([
'align'=>'right',
'width'=>'56',
'contentClasses'=>'py-1',
])

@php
 $alignmentClasses = match ($align) {
'left'=>'left-0 origin-top-left',
'top'=>'left-1/2 -translate-x-1/2 origin-top',
 default =>'right-0 origin-top-right',
 };

 $widthClass = match ($width) {
'48'=>'w-48',
'56'=>'w-56',
'64'=>'w-64',
'72'=>'w-72',
 default => $width,
 };
@endphp

<div class="relative"x-data="dropdownState()"@click.outside="close()"@keydown.escape.window="close()">
 <div @click="toggle()">
 {{ $trigger }}
 </div>

 <div
 x-cloak
 x-show="open"
 x-transition:enter="transition ease-out duration-150"
 x-transition:enter-start="opacity-0 scale-95"
 x-transition:enter-end="opacity-100 scale-100"
 x-transition:leave="transition ease-in duration-100"
 x-transition:leave-start="opacity-100 scale-100"
 x-transition:leave-end="opacity-0 scale-95"
 class="absolute z-50 mt-2 {{ $widthClass }} {{ $alignmentClasses }}"
 style="display: none;"
 >
 <div class="shell-card overflow-hidden p-1 {{ $contentClasses }}"@click="close()">
 {{ $content }}
 </div>
 </div>
</div>
