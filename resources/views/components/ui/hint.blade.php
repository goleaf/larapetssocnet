@props([
'error'=> null,
'message'=> null,
'id'=> null,
])
@if ((is_bool($error) ? $error : filled($error)) && (((is_string($error) ? $error : null) ?: $message) || $slot->isNotEmpty()))
 <p id="{{ $id }}" {{ $attributes->merge(['class'=>'mt-1 flex items-center gap-1 text-xs text-rose']) }}>
 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true">
 <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
 </svg>

 @if (((is_string($error) ? $error : null) ?: $message))
 <span>{{ (is_string($error) ? $error : null) ?: $message }}</span>
 @else
 {{ $slot }}
 @endif
 </p>
@elseif ($message || $slot->isNotEmpty())
 <p id="{{ $id }}" {{ $attributes->merge(['class'=>'mt-1 text-xs text-fur']) }}>
 @if ($message)
 {{ $message }}
 @else
 {{ $slot }}
 @endif
 </p>
@endif
