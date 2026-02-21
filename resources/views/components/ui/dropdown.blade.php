@props([
    'align' => 'right',
    'width' => '56',
    'contentClasses' => 'py-1',
])

<x-dropdown :align="$align" :width="$width" :content-classes="$contentClasses">
    <x-slot name="trigger">
        {{ $trigger }}
    </x-slot>

    <x-slot name="content">
        {{ $content }}
    </x-slot>
</x-dropdown>
