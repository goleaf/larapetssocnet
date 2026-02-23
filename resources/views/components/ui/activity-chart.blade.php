@props([
'data'=> [],
])

@if (count($data) > 0)
 @php
 $maxCount = max(array_column($data,'count'));
 $barWidth = count($data) > 0 ? (int) floor(100 / count($data)) - 2 : 14;
 $svgHeight = 60;
 @endphp

 <div {{ $attributes->merge(['class'=>'mt-3']) }}>
 <p class="mb-2 text-xs font-semibold text-fur">Activity</p>
 <svg viewBox="0 0 {{ count($data) * 18 }} {{ $svgHeight + 16 }}"class="w-full h-16"aria-label="Activity chart">
 @foreach ($data as $i => $item)
 @php
 $barHeight = $maxCount > 0 ? (int) round(($item['count'] / $maxCount) * $svgHeight) : 0;
 $barX = $i * 18 + 2;
 $barY = $svgHeight - $barHeight;
 @endphp
 <rect
 x="{{ $barX }}"
 y="{{ $barY }}"
 width="12"
 height="{{ max($barHeight, 2) }}"
 rx="3"
 fill="#E8834A"
 opacity="{{ $barHeight > 0 ?'1':'0.3'}}"
 />
 <text
 x="{{ $barX + 6 }}"
 y="{{ $svgHeight + 12 }}"
 text-anchor="middle"
 class="fill-fur"
 style="font-size: 7px; font-family:'DM Sans', sans-serif;"
 >{{ $item['month'] }}</text>
 @endforeach
 </svg>
 </div>
@endif
