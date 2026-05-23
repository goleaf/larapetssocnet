@php
 $pet = $pet ?? null;
 $personalityTagSuggestions = $personalityTagSuggestions ?? [];
 $personalityTagMax = $personalityTagMax ?? 10;
 $personalityTagsInitial = $personalityTagsInitial ?? [];
 $birthdateValue = $birthdateValue ?? null;
 $speciesOptions = $speciesOptions ?? [];
 $genderOptions = $genderOptions ?? [];
 $sizeOptions = $sizeOptions ?? [];
 $wizard = (bool) ($wizard ?? false);
@endphp

<div class="space-y-6">
 @if ($wizard)
 <section x-show="step === 1" x-transition data-ui="pet-create-wizard-step-basics" class="space-y-6">
 @endif
 <div
 class="grid gap-6 sm:grid-cols-2"
 x-data="petBreedAutocomplete({
 species: @js(old('species', $pet?->species ?? 'dog')),
 breed: @js(old('breed', $pet?->breed)),
 endpoint: @js(route('api.breeds.index'))
 })"
 >
 <div>
 <x-ui.input id="name" name="name" type="text" label="Pet name" :value="old('name', $pet?->name)" required/>
 </div>

 <div>
 <x-ui.select
 id="species"
 name="species"
 label="Species"
 :options="$speciesOptions"
 :selected="old('species', $pet?->species)"
 required
 x-model="species"
 @change="resetForSpecies"
 />
 </div>

 <div class="relative">
 <x-ui.input
 id="breed"
 name="breed"
 type="text"
 label="Breed"
 :value="old('breed', $pet?->breed)"
 x-model="breed"
 @input.debounce.300ms="search"
 @focus="search"
 autocomplete="off"
 />
 <div
 x-cloak
 x-show="open"
 @click.outside="open = false"
 class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white shadow-card"
 data-ui="pet-breed-autocomplete">
 <template x-for="breedOption in suggestions" :key="breedOption.id">
 <button type="button" class="block w-full px-3 py-2 text-left text-sm text-bark hover:bg-cream focus-visible:bg-cream focus-visible:outline-none" @click="selectBreed(breedOption.name)" x-text="breedOption.name"></button>
 </template>
 </div>
 </div>

 <div>
 <x-ui.select
 id="sex"
 name="sex"
 label="Sex"
 :options="$genderOptions"
 :selected="old('sex', old('gender', $pet?->sex))"
 />
 </div>

 <div>
 <x-ui.input id="birth_date" name="birth_date" type="date" label="Birth date" :value="$birthdateValue"/>
 </div>

 <div>
 <x-ui.input id="age_text" name="age_text" type="text" label="Approx age (if birth date unknown)" :value="old('age_text', $pet?->age_text)" placeholder="~2 years"/>
 </div>

 <div>
 <x-ui.select
 id="size"
 name="size"
 label="Size"
 :options="$sizeOptions"
 :selected="old('size', $pet?->size)"
 />
 </div>
 </div>

 @if ($wizard)
 </section>
 <section x-show="step === 2" x-transition data-ui="pet-create-wizard-step-story" class="space-y-6">
 @endif

 <div>
 <x-ui.textarea id="bio" name="bio" rows="5" label="Bio" :value="old('bio', $pet?->bio)"/>
 </div>

 <div
 x-data="{
 tags: @js($personalityTagsInitial),
 suggestions: @js($personalityTagSuggestions),
 max: @js($personalityTagMax),
 newTag: '',
 get normalizedNewTag() {
 const cleaned = this.newTag
 .toLowerCase()
 .replace(/[^a-z0-9 ]/gi, '')
 .replace(/\s+/g, ' ')
 .trim();

 return cleaned;
 },
 get canAddMore() {
 return this.tags.length < this.max;
 },
 get filteredSuggestions() {
 if (this.newTag.trim() === '') {
 return this.suggestions.filter((tag) => !this.tags.includes(tag)).slice(0, 8);
 }

 const search = this.normalizedNewTag;
 if (search === '') {
 return [];
 }

 return this.suggestions
 .filter((tag) => tag.includes(search) && !this.tags.includes(tag))
 .slice(0, 8);
 },
 addTag(tag = null) {
 const candidate = tag ?? this.normalizedNewTag;

 if (!candidate || !this.canAddMore) {
 this.newTag = '';
 return;
 }

 if (this.tags.includes(candidate)) {
 this.newTag = '';
 return;
 }

 this.tags.push(candidate);
 this.newTag = '';
 },
 removeTag(index) {
 this.tags.splice(index, 1);
 }
 }"
 >
 <x-ui.label for="personality_tags_input">Personality tags</x-ui.label>
 <div class="mt-1 flex flex-wrap gap-2">
 <template x-for="(tag, index) in tags" :key="tag">
 <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
 <span x-text="tag"></span>
 <button type="button" class="text-slate-400 hover:text-slate-600" @click="removeTag(index)" aria-label="Remove tag">
 &times;
 </button>
 </span>
 </template>
 </div>

 <div class="mt-2 flex flex-wrap items-center gap-2">
 <input
 id="personality_tags_input"
 type="text"
 class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 placeholder="Add a tag and press Enter"
 x-model="newTag"
 @keydown.enter.prevent="addTag()"
 @keydown.comma.prevent="addTag()"
 @blur="addTag()"
 :disabled="!canAddMore"
 />
 <span class="text-xs text-gray-500" x-text="tags.length + '/' + max + ' tags'"></span>
 </div>

 <div class="mt-2 flex flex-wrap gap-2" x-show="filteredSuggestions.length" x-transition>
 <template x-for="suggestion in filteredSuggestions" :key="suggestion">
 <button
 type="button"
 class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
 @click="addTag(suggestion)"
 x-text="suggestion"
 ></button>
 </template>
 </div>

 <p class="mt-1 text-xs text-gray-500">Pick up to {{ $personalityTagMax }} tags.</p>
 <x-ui.hint :error="$errors->first('personality_tags')" />
 <x-ui.hint :error="$errors->first('personality_tags.*')" />

 <input type="hidden" name="personality_tags[]" value="">
 <template x-for="tag in tags" :key="`input-${tag}`">
 <input type="hidden" name="personality_tags[]" :value="tag">
 </template>
 </div>

 @if ($wizard)
 </section>
 <section x-show="step === 3" x-transition data-ui="pet-create-wizard-step-photos" class="space-y-6">
 @endif

 @if (empty($pet))
 <div>
 <x-ui.file-upload
 id="avatar"
 name="avatar"
 label="Avatar"
 accept="image/jpeg,image/png,image/webp,image/gif"
 help="Upload a profile photo (max 5MB)."
 />
 <x-ui.hint :error="$errors->first('avatar')" />
 </div>
 @endif

 @if (empty($pet))
 <div>
 <x-ui.file-upload
 id="gallery_photos"
 name="gallery_photos[]"
 label="Photo Gallery"
 accept="image/jpeg,image/png,image/webp,image/gif"
 multiple
 help="Upload up to {{ (int) config('pets.gallery.max_upload', 5) }} photos, max 5MB each."
 />
 </div>
 @endif

 <div class="space-y-3">
 <x-ui.checkbox name="is_public" label="Public profile" :checked="old('is_public', $pet?->is_public ?? true)"/>

 <x-ui.checkbox name="is_adoptable" label="Available for adoption" :checked="old('is_adoptable', old('is_for_adoption', $pet?->is_adoptable ?? false))"/>
 <p class="ml-7 text-xs text-gray-500">{{ __('pets.adoption.toggle_hint') }}</p>

 <x-ui.checkbox name="is_deceased" label="Mark as deceased (Rainbow Bridge)" :checked="old('is_deceased', $pet?->is_deceased ?? false)"/>
 </div>
 @if ($wizard)
 </section>
 @endif
</div>
