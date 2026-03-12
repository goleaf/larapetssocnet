@props([
'align'=>'right',
'width'=>'48',
'contentClasses'=>'py-1',
])

<div class="relative" x-data="dropdownState(false)" @click.outside="close()" @keydown.escape.window="close()">
 <div class="inline-block" @click="toggle()">
 {{ $trigger ?? '' }}
 </div>

 <div
 x-show="open"
 x-cloak
 style="display: none;"
 x-transition:enter="transition ease-out duration-200"
 x-transition:enter-start="transform opacity-0 scale-95"
 x-transition:enter-end="transform opacity-100 scale-100"
 x-transition:leave="transition ease-in duration-75"
 x-transition:leave-start="transform opacity-100 scale-100"
 x-transition:leave-end="transform opacity-0 scale-95"
 class="absolute z-50 mt-2 {{ match ((string) $width) {
    'auto' => 'w-auto whitespace-nowrap',
    '40' => 'w-40',
    '48' => 'w-48',
    '56' => 'w-56',
    '64' => 'w-64',
    '72' => 'w-72',
    default => 'w-48',
 } }} {{ match ((string) $align) {
    'left' => 'left-0 origin-top-left',
    'center' => 'left-1/2 -translate-x-1/2 origin-top',
    default => 'right-0 origin-top-right',
 } }}"
 @click="close()"
 >
 <div class="rounded-[var(--radius-soft)] border border-whisker/30 bg-[color:var(--surface-dropdown)] shadow-card-hover {{ $contentClasses }}">
 {{ $content ?? '' }}
 </div>
 </div>
</div>
