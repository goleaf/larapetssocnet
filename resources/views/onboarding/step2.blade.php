<x-app-layout>
 <x-slot name="header">
 <div>
 <h1 class="shell-title text-xl">{{ __('en.onboarding_step_2_of_3') }}</h1>
 <p class="mt-1 text-sm shell-text-muted">{{ __('en.add_your_first_pet_now_or_skip_and_do_it_later') }}</p>
 </div>
 </x-slot>

 <div class="shell-card p-6 sm:p-8">
 <form method="POST" action="{{ route('onboarding.store', ['step'=> 2]) }}" class="space-y-5">
 @csrf

 <div>
 <label for="pet_name" class="mb-1 block text-sm font-semibold">{{ __('en.pet_name') }}</label>
 <input id="pet_name" name="pet_name" type="text" class="form-input" value="{{ old('pet_name') }}" :placeholder="__('en.milo')" />
 <x-input-error :messages="$errors->get('pet_name')" class="mt-2" />
 </div>

 <div>
 <label for="pet_species" class="mb-1 block text-sm font-semibold">{{ __('en.species') }}</label>
 <select id="pet_species" name="pet_species" class="form-select">
 <option value="">{{ __('en.select_species_optional') }}</option>
 @foreach ($speciesOptions as $species)
 <option value="{{ $species }}"@selected(old('pet_species') === $species)>{{ $species }}</option>
 @endforeach
 </select>
 <x-input-error :messages="$errors->get('pet_species')" class="mt-2" />
 </div>

 <div>
 <label for="pet_bio" class="mb-1 block text-sm font-semibold">{{ __('en.short_bio') }}</label>
 <textarea id="pet_bio" name="pet_bio" rows="4" class="form-textarea" :placeholder="__('en.friendly_playful_and_loves_park_walks')">{{ old('pet_bio') }}</textarea>
 <x-input-error :messages="$errors->get('pet_bio')" class="mt-2" />
 </div>

 <div class="flex flex-wrap items-center justify-between gap-3">
 <button type="submit" class="btn-base btn-primary">{{ __('en.save_and_continue') }}</button>
 <button type="submit" form="skip-step-2" class="btn-base btn-ghost">{{ __('en.skip_this_step') }}</button>
 </div>
 </form>

 <form id="skip-step-2" method="POST" action="{{ route('onboarding.skip', ['step'=> 2]) }}">
 @csrf
 </form>
 </div>
</x-app-layout>
