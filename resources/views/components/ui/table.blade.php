@props([
'headers'=> [],
'headings'=> [],
'striped'=> false,
'compact'=> false,
'empty'=>'No records found.',
'hasRows'=> true,
])

@php
 $resolvedHeaders = ! empty($headers) ? $headers : $headings;
 $rowSlot = isset($rows) ? $rows : $slot;
 $hasRenderableRows = $hasRows && isset($rowSlot) && trim((string) $rowSlot) !=='';
 $padding = $compact ?'px-3 py-2':'px-4 py-3';
@endphp

<div {{ $attributes->merge(['class'=>'shell-card overflow-hidden text-sm']) }}>
 <div class="w-full overflow-x-auto">
 <table class="w-full border-collapse text-left">
 @if(! empty($resolvedHeaders))
 <thead>
 <tr class="border-b border-whisker/40 bg-cream">
 @foreach($resolvedHeaders as $header)
 @php
 $label = is_array($header) ? ($header['label'] ??'') : $header;
 $class = is_array($header) ? ($header['class'] ??'') :'';
 $align = is_array($header) ? ($header['align'] ??'left') :'left';

 $alignClass = match ($align) {
'center'=>'text-center',
'right'=>'text-right',
 default =>'text-left',
 };
 @endphp

 <th scope="col" class="{{ $padding }} {{ $alignClass }} text-xs font-semibold uppercase tracking-wide text-fur {{ $class }}">
 {{ $label }}
 </th>
 @endforeach
 </tr>
 </thead>
 @endif

 @if($hasRenderableRows)
 <tbody class="divide-y divide-whisker/30 {{ $striped ?'[&>tr:nth-child(even)]:bg-cream/40':''}}">
 {{ $rowSlot }}
 </tbody>
 @else
 <tbody>
 <tr>
 <td colspan="{{ max(count($resolvedHeaders), 1) }}" class="px-4 py-12 text-center text-fur">
 <div class="flex flex-col items-center justify-center">
 <span class="mb-2 text-3xl opacity-50" aria-hidden="true">🐾</span>
 <p>{{ $empty }}</p>
 </div>
 </td>
 </tr>
 </tbody>
 @endif
 </table>
 </div>
</div>
