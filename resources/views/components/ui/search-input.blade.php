@props([
'name'=>'query',
'id'=> null,
'placeholder'=>'Search...',
'value'=> null,
'action'=>'',
])

@php
 $fieldId = $id ?: ($name ?:'search-'.\Illuminate\Support\Str::random(6));
 $resolvedValue = $value ?? request()->query($name,'');
@endphp

<form
 action="{{ $action }}"
 method="GET"
 role="search"
 {{ $attributes->except('class')->merge(['class'=>'relative w-full max-w-full lg:w-64 '.trim((string) $attributes->get('class'))]) }}
>
 <label for="{{ $fieldId }}" class="sr-only">Search</label>

 <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-fur" aria-hidden="true">
 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
 <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
 </svg>
 </span>

 <input
 id="{{ $fieldId }}"
 type="search"
 name="{{ $name }}"
 value="{{ $resolvedValue }}"
 placeholder="{{ $placeholder }}"
 class="h-[var(--control-height-md)] w-full rounded-[var(--radius-control)] border border-whisker bg-[color:var(--surface-form)] py-2 pl-10 pr-3 text-sm text-bark placeholder:text-whisker transition-all duration-150 focus:border-paw focus:bg-warm-white focus:outline-none focus:shadow-input"
 />
</form>
