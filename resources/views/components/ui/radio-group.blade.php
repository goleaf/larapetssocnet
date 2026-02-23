@props([
'name'=> null,
'id'=> null,
'label'=> null,
'options'=> [],
'value'=> null,
'selected'=> null,
'required'=> false,
'disabled'=> false,
'error'=> null,
'hint'=> null,
'description'=> null,
])

@php
 $fieldName = $name ?: $attributes->get('name');
 $fieldId = $id ?: $attributes->get('id') ?: ($fieldName ?:'radio-group-'.\Illuminate\Support\Str::random(6));
 $resolvedHint = $description ?? $hint;

 $selectedValue = $value ?? $selected;

 if ($selectedValue === null && $fieldName) {
 $selectedValue = old($fieldName);
 }

 $resolvedError = $error;

 if ($resolvedError === null && $fieldName) {
 $resolvedError = $errors->first($fieldName);
 }

 $hasError = filled($resolvedError);
 $hintId = $fieldId.'-hint';

 $normalizedOptions = [];

 foreach ($options as $key => $option) {
 if (is_array($option)) {
 $optionValue = $option['value'] ?? $key;
 $optionLabel = $option['label'] ?? (string) $optionValue;
 $optionDescription = $option['description'] ?? null;
 $optionDisabled = (bool) ($option['disabled'] ?? false);
 } else {
 $optionValue = is_int($key) ? $option : $key;
 $optionLabel = (string) $option;
 $optionDescription = null;
 $optionDisabled = false;
 }

 $normalizedOptions[] = [
'value'=> $optionValue,
'label'=> $optionLabel,
'description'=> $optionDescription,
'disabled'=> $optionDisabled,
 ];
 }

 $xModel = $attributes->get('x-model');
 $wrapperAttributes = $attributes->except(['x-model','name','id']);
@endphp

<fieldset {{ $wrapperAttributes->merge(['class'=>'flex flex-col gap-2']) }}>
 @if ($label)
 <legend class="text-sm font-medium text-bark">
 {{ $label }}
 @if ($required)
 <span class="ml-0.5 text-rose"aria-hidden="true">*</span>
 <span class="sr-only">required</span>
 @endif
 </legend>
 @endif

 <div class="space-y-2">
 @foreach ($normalizedOptions as $index => $option)
 @php
 $optionId = $fieldId.'-'.\Illuminate\Support\Str::slug((string) $option['value']).'-'.$index;
 $optionSelected = (string) $selectedValue === (string) $option['value'];
 $optionDisabled = $disabled || $option['disabled'];
 @endphp

 <label
 for="{{ $optionId }}"
 class="relative flex w-full cursor-pointer items-start gap-4 rounded-lg border bg-warm-white p-3 transition-all duration-150 hover:bg-cream"
 >
 <input
 type="radio"
 id="{{ $optionId }}"
 @if ($fieldName)
 name="{{ $fieldName }}"
 @endif
 value="{{ $option['value'] }}"
 class="peer sr-only"
 @if ($xModel)
 x-model="{{ $xModel }}"
 @endif
 @checked($optionSelected)
 @if ($required)
 required
 @endif
 @if ($optionDisabled)
 disabled
 @endif
 @if ($hasError)
 aria-invalid="true"
 @endif
 @if ($hasError || $resolvedHint)
 aria-describedby="{{ $hintId }}"
 @endif
 />

 <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-pill border border-whisker bg-warm-white transition peer-checked:border-paw peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-paw">
 <span class="h-2.5 w-2.5 rounded-pill bg-paw scale-0 transition peer-checked:scale-100"></span>
 </span>

 <span class="min-w-0 flex-1">
 <span class="block text-sm font-medium text-bark">{{ $option['label'] }}</span>
 @if ($option['description'])
 <span class="mt-0.5 block text-xs text-fur">{{ $option['description'] }}</span>
 @endif
 </span>
 </label>
 @endforeach
 </div>

 @if ($hasError || $resolvedHint)
 <x-ui.hint :id="$hintId":error="$resolvedError":message="$resolvedHint"/>
 @endif
</fieldset>
