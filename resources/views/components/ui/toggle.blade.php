@props(['name','label','description'=> null,'checked'=> false,'value'=>'1'])

<div x-data="{ checked: {{ $checked ?'true':'false'}} }"class="flex items-start justify-between">
 <div class="flex-grow">
 <label for="{{ $name }}"class="text-sm font-medium text-gray-900">{{ $label }}</label>
 @if($description)
 <p class="text-sm text-gray-500">{{ $description }}</p>
 @endif
 </div>
 <div class="ml-4 flex-shrink-0">
 <button type="button"@click="checked = !checked":class="{'bg-indigo-600': checked,'bg-gray-200': !checked }"
 class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
 role="switch":aria-checked="checked.toString()">
 <span class="sr-only">Toggle {{ $label }}</span>
 <span aria-hidden="true":class="{'translate-x-5': checked,'translate-x-0': !checked }"
 class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
 </button>
 <input type="hidden"name="{{ $name }}"x-bind:value="checked ?'1':'0'"/>
 </div>
</div>