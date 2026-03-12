@props([
'headers'=> [],
'headings'=> [],
'striped'=> false,
'compact'=> false,
'empty'=>'No records found.',
'hasRows'=> true,
])

<div {{ $attributes->merge(['class'=>'shell-card overflow-hidden text-sm']) }}>
 <div class="w-full overflow-x-auto">
 <table class="w-full border-collapse text-left">
 @if(! empty(! empty($headers) ? $headers : $headings))
 <thead>
 <tr class="border-b border-whisker/40 bg-cream">
 @foreach((! empty($headers) ? $headers : $headings) as $header)

 <th scope="col" class="{{ $compact ?'px-3 py-2':'px-4 py-3' }} {{ match (is_array($header) ? ($header['align'] ?? 'left') : 'left') {
    'center' => 'text-center',
    'right' => 'text-right',
    default => 'text-left',
 } }} text-xs font-semibold uppercase tracking-wide text-fur {{ is_array($header) ? ($header['class'] ?? '') : '' }}">
 {{ is_array($header) ? ($header['label'] ?? '') : $header }}
 </th>
 @endforeach
 </tr>
 </thead>
 @endif

 @if($hasRows && trim((string) (isset($rows) ? $rows : $slot)) !=='')
 <tbody class="divide-y divide-whisker/30 {{ $striped ?'[&>tr:nth-child(even)]:bg-cream/40':''}}">
 {{ isset($rows) ? $rows : $slot }}
 </tbody>
 @else
 <tbody>
 <tr>
 <td colspan="{{ max(count(! empty($headers) ? $headers : $headings), 1) }}" class="px-4 py-12 text-center text-fur">
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
