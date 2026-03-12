@props([
'align'=>'left',
'compact'=> false,
])
<td {{ $attributes->merge(['class'=> ($compact ?'px-3 py-2':'px-4 py-3').' '.match ((string) $align) {
 'center' => 'text-center',
 'right' => 'text-right',
 default => 'text-left',
}.' text-bark']) }}>
 {{ $slot }}
</td>
