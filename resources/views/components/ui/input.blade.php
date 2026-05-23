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

 $baseClasses = 'form-input h-[var(--control-height-md)] w-full text-sm';

 if ($hasError) {
 $stateClasses = 'border-rose text-rose focus:border-rose';
 } elseif ($disabled) {
 $stateClasses = 'cursor-not-allowed opacity-60';
 } else {
 $stateClasses = 'focus:border-paw';
 }

 $classes = \Illuminate\Support\Arr::toCssClasses([
 $baseClasses,
 $stateClasses,
'pl-10'=> $prefix,
'pr-10'=> $suffix,
 ]);

 $controlAttributes = $attributes->except(['class','name','id','value']);

 if (
 strtolower((string) $type) === 'password'
 && strtolower((string) $attributes->get('autocomplete')) === 'new-password'
 && ! array_key_exists('passwordrules', $controlAttributes->all())
 ) {
 $controlAttributes = $controlAttributes->merge([
 'passwordrules' => \App\Support\Auth\PasswordPolicy::htmlRules(),
 ]);
 }
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
 @if ($fieldName)
 name="{{ $fieldName }}"
 @endif
 @if ($fieldValue !== null)
 value="{{ $fieldValue }}"
 @endif
 @if ($required)
 required
 @endif
 @if ($disabled)
 disabled
 @endif
 @if ($hasError)
 aria-invalid="true"
 @endif
 @if ($hasError || $hint)
 aria-describedby="{{ $hintId }}"
 @endif
 {{ $controlAttributes->merge(['class'=> $classes]) }}
 />

 @if ($suffix)
 <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-fur" aria-hidden="true">
 {{ $suffix }}
 </div>
 @endif
 </div>

 @if ($hasError || $hint)
 <x-ui.hint :id="$hintId" :error="$resolvedError" :message="$hint"/>
 @endif
</div>
