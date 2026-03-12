@props([
'action'=>'#',
'method'=>'GET',
'name'=>'q',
'value'=> null,
'placeholder'=>'Search...',
'buttonLabel'=>'Search',
])

<form
 method="{{ in_array(strtoupper($method), ['GET', 'POST'], true) ? strtoupper($method) : 'POST' }}"
 action="{{ $action }}"
 {{ $attributes->merge(['class'=>'flex items-center gap-2']) }}
 x-data="searchFormState(@js(($value ?? request($name)) ?? ''))"
>
 @if ((in_array(strtoupper($method), ['GET', 'POST'], true) ? strtoupper($method) : 'POST') !== 'GET')
 @csrf
 @endif

 @if (! in_array(strtoupper($method), ['GET','POST'], true))
 @method(strtoupper($method))
 @endif

 <div class="flex-1">
 <x-ui.input
 type="search"
 name="{{ $name }}"
 x-model="query"
 :value="$value ?? request($name)"
 :placeholder="$placeholder"
 autocomplete="off"
 :prefix="'<svg class=\"h-4 w-4\" viewBox=\"0 0 20 20\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.7\"><circle cx=\"8.5\" cy=\"8.5\" r=\"5.5\"/><path d=\"m13 13 4 4\" stroke-linecap=\"round\"/></svg>'"
 />
 </div>

 <x-ui.button type="submit" variant="primary" class="whitespace-nowrap px-3.5">
 {{ $buttonLabel }}
 </x-ui.button>
</form>
