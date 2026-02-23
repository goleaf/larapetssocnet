<x-app-layout>
 <x-slot name="header">
 <div>
 <h1 class="shell-title text-xl">Onboarding: Step 2 of 3</h1>
 <p class="mt-1 text-sm shell-text-muted">Add your first pet now or skip and do it later.</p>
 </div>
 </x-slot>

 <div class="shell-card p-6 sm:p-8">
 <form method="POST"action="{{ route('onboarding.store', ['step'=> 2]) }}"class="space-y-5">
 @csrf

 <div>
 <label for="pet_name"class="mb-1 block text-sm font-semibold">Pet Name</label>
 <input id="pet_name"name="pet_name"type="text"class="form-input"value="{{ old('pet_name') }}"placeholder="Milo"/>
 <x-input-error :messages="$errors->get('pet_name')"class="mt-2"/>
 </div>

 <div>
 <label for="pet_species"class="mb-1 block text-sm font-semibold">Species</label>
 <select id="pet_species"name="pet_species"class="form-select">
 <option value="">Select species (optional)</option>
 @foreach ($speciesOptions as $species)
 <option value="{{ $species }}"@selected(old('pet_species') === $species)>{{ $species }}</option>
 @endforeach
 </select>
 <x-input-error :messages="$errors->get('pet_species')"class="mt-2"/>
 </div>

 <div>
 <label for="pet_bio"class="mb-1 block text-sm font-semibold">Short Bio</label>
 <textarea id="pet_bio"name="pet_bio"rows="4"class="form-textarea"placeholder="Friendly, playful, and loves park walks.">{{ old('pet_bio') }}</textarea>
 <x-input-error :messages="$errors->get('pet_bio')"class="mt-2"/>
 </div>

 <div class="flex flex-wrap items-center justify-between gap-3">
 <button type="submit"class="btn-base btn-primary">Save and Continue</button>
 <button type="submit"form="skip-step-2"class="btn-base btn-ghost">Skip this step</button>
 </div>
 </form>

 <form id="skip-step-2"method="POST"action="{{ route('onboarding.skip', ['step'=> 2]) }}">
 @csrf
 </form>
 </div>
</x-app-layout>
