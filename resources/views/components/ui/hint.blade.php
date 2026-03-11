@props([
'error'=> null,
'message'=> null,
'id'=> null,
])

@php
 $hasError = is_bool($error) ? $error : filled($error);
 $errorMessage = is_string($error) ? $error : null;
 $slotHasContent = $slot->isNotEmpty();

 $text = null;

 if ($hasError) {
 $text = $errorMessage ?: $message;
 } else {
 $text = $message;
 }
@endphp

@if ($hasError && ($text || $slotHasContent))
 <p id="{{ $id }}"{{ $attributes->merge(['class'=>'mt-1 flex items-center gap-1 text-xs text-rose']) }}>
 <svg xmlns="http://www.w3.org/2000/svg"viewBox="0 0 20 20"fill="currentColor"class="h-4 w-4 shrink-0"aria-hidden="true">
 <path fill-rule="evenodd"d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z"clip-rule="evenodd"/>
 </svg>

 @if ($text)
 <span>{{ $text }}</span>
 @else
 {{ $slot }}
 @endif
 </p>
@elseif ($text || $slotHasContent)
 <p id="{{ $id }}"{{ $attributes->merge(['class'=>'mt-1 text-xs text-fur']) }}>
 @if ($text)
 {{ $text }}
 @else
 {{ $slot }}
 @endif
 </p>
@endif
