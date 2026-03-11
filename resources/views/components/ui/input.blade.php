@props([
'name'=> null,
'id'=> null,
'label'=> null,
'type'=>'text',
'value'=> null,
'required'=> false,
'disabled'=> false,
'error'=> null,
'hint'=> null,
'prefix'=> null,
'suffix'=> null,
])

@php
 $fieldName = $name ?: $attributes->get('name');
 $fieldId = $id ?: $attributes->get('id') ?: ($fieldName ?:'input-'.\Illuminate\Support\Str::random(6));

 $fieldValue = $value;

 if ($fieldValue === null) {
 $fieldValue = $attributes->get('value');
 }

 if ($fieldValue === null && $fieldName) {
 $fieldValue = old($fieldName);
 }

 $resolvedError = $error;

 if ($resolvedError === null && $fieldName) {
 $resolvedError = $errors->first($fieldName);
 }

 $hasError = filled($resolvedError);
 $hintId = $fieldId.'-hint';

 $baseClasses ='w-full rounded-md border px-3.5 py-2.5 text-sm text-bark placeholder:text-whisker transition-all duration-150 focus:outline-none';

 if ($hasError) {
 $stateClasses ='border-rose bg-rose-light/20 focus:border-rose focus:shadow-[0_0_0_3px_rgba(201,74,90,0.15)]';
 } elseif ($disabled) {
 $stateClasses ='border-whisker bg-cream opacity-60 cursor-not-allowed';
 } else {
 $stateClasses ='border-whisker bg-warm-white focus:border-paw focus:shadow-input';
 }

 $classes = \Illuminate\Support\Arr::toCssClasses([
 $baseClasses,
 $stateClasses,
'pl-10'=> $prefix,
'pr-10'=> $suffix,
 ]);

 $controlAttributes = $attributes->except(['class','name','id','value']);

 $inputName = $fieldName ? 'name="'.$fieldName.'"' : '';
 $inputValue = $fieldValue !== null ? 'value="'.e($fieldValue).'"' : '';
@endphp

<div {{ $attributes->only('class')->merge(['class'=>'flex flex-col gap-1']) }}>
 @if ($label)
 <x-ui.label :for="$fieldId" :required="$required">{{ $label }}</x-ui.label>
 @endif

 <div class="relative">
 @if ($prefix)
 <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-fur" aria-hidden="true">
 {{ $prefix }}
 </div>
 @endif

 <input
 type="{{ $type }}"
 id="{{ $fieldId }}"
 @if ($fieldName) name="{{ $fieldName }}" @endif
 @if ($fieldValue !== null) value="{{ $fieldValue }}" @endif
 @if ($required) required @endif
 @if ($disabled) disabled @endif
 @if ($hasError) aria-invalid="true" @endif
 @if ($hasError || $hint) aria-describedby="{{ $hintId }}" @endif
 {{ $controlAttributes->merge(['class'=> $classes]) }}
  />

 @if ($suffix)
 <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-fur" aria-hidden="true">
 {{ $suffix }}
 </div>
 @endif
 </div>

 @if ($hasError || $hint)
 <x-ui.hint :id="$hintId" :error="$resolvedError" :message="$hint"  />
 @endif
</div>
