@props([
'data'=> [],
])

@if (count($data) > 0)
 <div {{ $attributes->merge(['class'=>'mt-3']) }}>
 <p class="mb-2 text-xs font-semibold text-fur">Activity</p>
 <svg viewBox="0 0 {{ count($data) * 18 }} 76" class="w-full h-16" aria-label="Activity chart">
 @foreach ($data as $i => $item)
 <rect
 x="{{ $i * 18 + 2 }}"
 y="{{ 60 - ((max(array_column($data, 'count')) > 0) ? (int) round((($item['count'] ?? 0) / max(array_column($data, 'count'))) * 60) : 0) }}"
 width="12"
 height="{{ max(((max(array_column($data, 'count')) > 0) ? (int) round((($item['count'] ?? 0) / max(array_column($data, 'count'))) * 60) : 0), 2) }}"
 rx="3"
 fill="var(--color-paw)"
 opacity="{{ ((max(array_column($data, 'count')) > 0) ? (int) round((($item['count'] ?? 0) / max(array_column($data, 'count'))) * 60) : 0) > 0 ? '1' : '0.3' }}"
 />
 <text
 x="{{ $i * 18 + 8 }}"
 y="72"
 text-anchor="middle"
 class="fill-fur"
 style="font-size: 7px; font-family: var(--font-body);"
 >{{ $item['month'] }}</text>
 @endforeach
 </svg>
 </div>
@endif
