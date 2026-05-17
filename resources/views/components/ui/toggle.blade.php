@props(['name','label','description'=> null,'checked'=> false,'value'=>'1'])

@php
 $fieldId = 'toggle-'.\Illuminate\Support\Str::slug(str_replace(['[',']'], ['-',''], $name));
 $labelId = $fieldId.'-label';
 $descriptionId = $fieldId.'-description';
@endphp

<div x-data="{ checked: {{ $checked ?'true':'false'}} }" class="flex min-h-16 items-center justify-between gap-4 rounded-[var(--radius-soft)] border border-whisker/30 bg-[color:var(--surface-form)] px-3 py-3" data-ui="settings-toggle">
 <div class="min-w-0 flex-grow">
 <p id="{{ $labelId }}" class="text-sm font-medium text-bark">{{ $label }}</p>
 @if($description)
 <p id="{{ $descriptionId }}" class="mt-0.5 text-sm text-fur">{{ $description }}</p>
 @endif
 </div>
 <div class="ml-4 flex-shrink-0">
 <button
 id="{{ $fieldId }}"
 type="button"
 @click="checked = !checked"
 :class="{'bg-paw border-paw-dark': checked,'bg-cream border-whisker/50': !checked }"
 class="relative inline-flex min-h-11 w-14 flex-shrink-0 cursor-pointer items-center rounded-pill border px-1 transition-colors duration-200 ease-in-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 role="switch"
 :aria-checked="checked.toString()"
 aria-labelledby="{{ $labelId }}"
 @if($description)
 aria-describedby="{{ $descriptionId }}"
 @endif
 >
 <span class="sr-only">Toggle {{ $label }}</span>
 <span aria-hidden="true" :class="{'translate-x-6': checked,'translate-x-0': !checked }"
 class="pointer-events-none inline-block h-6 w-6 transform rounded-pill bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
 </button>
 <input type="hidden" name="{{ $name }}" x-bind:value="checked ?'1':'0'"/>
 </div>
</div>
