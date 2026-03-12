@props([
'name'=> null,
'id'=> null,
'label'=> null,
'value'=>'1',
'checked'=> false,
'required'=> false,
'disabled'=> false,
'hint'=> null,
'description'=> null,
'error'=> null,
])

@php
 $fieldName = $name ?: $attributes->get('name');
 $fieldId = $id ?: $attributes->get('id') ?: ($fieldName ?:'checkbox-'.\Illuminate\Support\Str::random(6));
 $resolvedDescription = $description ?? $hint;

 $resolvedError = $error;

 if ($resolvedError === null && $fieldName) {
 $resolvedError = $errors->first($fieldName);
 }

 $oldValue = $fieldName ? old($fieldName) : null;

 if ($oldValue !== null) {
 if (is_array($oldValue)) {
 $isChecked = in_array($value, $oldValue, false);
 } else {
 $isChecked = (string) $oldValue === (string) $value || in_array($oldValue, [true, 1,'1','on'], true);
 }
 } else {
 $isChecked = (bool) $checked;
 }

 $hasError = filled($resolvedError);
 $hintId = $fieldId.'-hint';

 $controlAttributes = $attributes->except(['class','name','id','value','checked']);
@endphp

<div {{ $attributes->only('class')->merge(['class'=>'flex items-start gap-3']) }}>
 <input
 type="checkbox"
 id="{{ $fieldId }}"
 @if ($fieldName)
 name="{{ $fieldName }}"
 @endif
 value="{{ $value }}"
 class="mt-0.5 h-4 w-4 rounded-[var(--radius-control)] border-whisker bg-[color:var(--surface-form)] text-paw focus:ring-paw"
 @checked($isChecked)
 @if ($required)
 required
 @endif
 @if ($disabled)
 disabled
 @endif
 @if ($hasError)
 aria-invalid="true"
 @endif
 @if ($hasError || $resolvedDescription)
 aria-describedby="{{ $hintId }}"
 @endif
 {{ $controlAttributes }}
 />

 <div class="min-w-0 space-y-1">
 @if ($label)
 <label
 for="{{ $fieldId }}"
 class="block text-sm font-medium {{ $disabled ?'text-whisker':'text-bark'}}"
 >
 {{ $label }}
 </label>
 @endif

 @if ($hasError || $resolvedDescription)
 <x-ui.hint :id="$hintId" :error="$resolvedError" :message="$resolvedDescription"/>
 @endif
 </div>
</div>
