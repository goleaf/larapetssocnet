@props([
'for'=> null,
'required'=> false,
])

<label
 @if ($for)
 for="{{ $for }}"
 @endif
 {{ $attributes->merge(['class'=>'block text-sm font-medium text-bark']) }}
>
 {{ $slot }}

 @if ($required)
 <span class="ml-0.5 text-rose" aria-hidden="true">*</span>
 <span class="sr-only">required</span>
 @endif
</label>
