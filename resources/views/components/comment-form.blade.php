@props([
'action'=>'#',
'method'=>'POST',
'name'=>'comment',
'placeholder'=>'Add a thoughtful comment...',
'buttonLabel'=>'Comment',
'compact'=> false,
])

@php
 $httpMethod = strtoupper($method);
 $formMethod = in_array($httpMethod, ['GET','POST'], true) ? $httpMethod :'POST';
@endphp

<form method="{{ $formMethod }}" action="{{ $action }}" {{ $attributes->merge(['class'=>'space-y-3']) }}>
 @if ($formMethod !=='GET')
 @csrf
 @endif

 @if (! in_array($httpMethod, ['GET','POST'], true))
 @method($httpMethod)
 @endif

 <textarea
 name="{{ $name }}"
 rows="{{ $compact ? 2 : 3 }}"
 class="form-textarea"
 placeholder="{{ $placeholder }}"
 >{{ old($name) }}</textarea>

 <div class="flex items-center justify-between gap-3">
 <span class="text-xs shell-text-muted">Be kind and helpful to pet families.</span>
 <button type="submit" class="btn-base btn-primary">
 {{ $buttonLabel }}
 </button>
 </div>
</form>
