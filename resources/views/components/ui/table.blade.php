@props([
    'headers' => [], /* array of strings or ['label', 'class', 'align'] */
    'striped' => false,
    'compact' => false,
    'empty' => 'No records found.',
    'hasRows' => true,
])

<div class="bg-warm-white rounded-lg shadow-card border border-whisker/30 overflow-hidden text-sm">
    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-cream border-b border-whisker/40">
                    @foreach($headers as $header)
                        @php
                            $label = is_array($header) ? ($header['label'] ?? '') : $header;
                            $class = is_array($header) ? ($header['class'] ?? '') : '';
                            $align = is_array($header) ? ($header['align'] ?? 'left') : 'left';
                            
                            $alignClass = match($align) {
                                'center' => 'text-center',
                                'right' => 'text-right',
                                default => 'text-left',
                            };
                            
                            $padding = $compact ? 'px-3 py-2' : 'px-4 py-3';
                        @endphp
                        <th class="{{ $padding }} text-xs uppercase tracking-wide font-semibold text-fur {{ $alignClass }} {{ $class }}" scope="col">
                            {{ $label }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            
            @if(isset($rows) && $rows->isNotEmpty())
                <tbody class="divide-y divide-whisker/30 {{ $striped ? 'striped-table' : '' }}">
                    {{ $rows }}
                </tbody>
            @elseif($hasRows && isset($slot) && $slot->isNotEmpty())
                <tbody class="divide-y divide-whisker/30 {{ $striped ? 'striped-table' : '' }}">
                    {{ $slot }}
                </tbody>
            @else
                <tbody>
                    <tr>
                        <td colspan="{{ count($headers) ?: 1 }}" class="px-4 py-12 text-center text-fur">
                            <div class="flex flex-col items-center justify-center">
                                <span class="text-3xl mb-2 opacity-50">🐾</span>
                                <p>{{ $empty }}</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            @endif
        </table>
    </div>
</div>

@if($striped)
<style>
    .striped-table tr:nth-child(even) {
        background-color: #FDF6EC; /* cream */
    }
</style>
@endif
