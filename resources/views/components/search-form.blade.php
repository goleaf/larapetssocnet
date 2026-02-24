@props([
'action'=>'#',
'method'=>'GET',
'name'=>'q',
'value'=> null,
'placeholder'=>'Search...',
'buttonLabel'=>'Search',
])

@php
 $httpMethod = strtoupper($method);
 $formMethod = in_array($httpMethod, ['GET','POST'], true) ? $httpMethod :'POST';
 $queryValue = $value ?? request($name);
@endphp

<form
 method="{{ $formMethod }}"
 action="{{ $action }}"
 {{ $attributes->merge(['class'=>'flex items-center gap-2']) }}
 x-data="searchFormState(@js($queryValue ??''))"
>
 @if ($formMethod !=='GET')
 @csrf
 @endif

 @if (! in_array($httpMethod, ['GET','POST'], true))
 @method($httpMethod)
 @endif

 <div class="relative flex-1">
 <span class="pointer-events-none absolute inset-y-0 left-3 inline-flex items-center shell-text-muted">
 <svg class="h-4 w-4"viewBox="0 0 20 20"fill="none"stroke="currentColor"stroke-width="1.7">
 <circle cx="8.5"cy="8.5"r="5.5"/>
 <path d="m13 13 4 4"stroke-linecap="round"/>
 </svg>
 </span>

 <input
 type="search"
 name="{{ $name }}"
 x-model="query"
 value="{{ $queryValue }}"
 class="form-input w-full pl-9"
 placeholder="{{ $placeholder }}"
 autocomplete="off"
 >
 </div>

 <button type="submit" class="btn-base btn-primary whitespace-nowrap px-3.5 py-2 text-sm">
 {{ $buttonLabel }}
 </button>
</form>
