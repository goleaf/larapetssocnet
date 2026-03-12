@props(['name','label','description'=> null,'checked'=> false,'value'=>'1'])

<div x-data="{ checked: {{ $checked ?'true':'false'}} }" class="flex items-start justify-between gap-4">
 <div class="flex-grow">
 <label for="{{ $name }}" class="text-sm font-medium text-bark">{{ $label }}</label>
 @if($description)
 <p class="text-sm text-fur">{{ $description }}</p>
 @endif
 </div>
 <div class="ml-4 flex-shrink-0">
 <button type="button" @click="checked = !checked" :class=\"{'bg-paw border-paw-dark': checked,'bg-cream border-whisker/50': !checked }\"
 class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-[var(--radius-soft)] border transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-paw focus:ring-offset-2"
 role="switch" :aria-checked="checked.toString()">
 <span class="sr-only">Toggle {{ $label }}</span>
 <span aria-hidden="true" :class=\"{'translate-x-5': checked,'translate-x-0': !checked }\"
 class="pointer-events-none inline-block h-5 w-5 transform rounded-[2px] bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
 </button>
 <input type="hidden" name="{{ $name }}" x-bind:value="checked ?'1':'0'"/>
 </div>
</div>
