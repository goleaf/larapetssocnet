<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Onboarding: Step 2 of 3" description="Add your first pet now or skip and do it later." icon="🐾" />
 </x-slot>

 <x-ui.card padding="lg">
 <form method="POST" action="{{ route('onboarding.store', ['step'=> 2]) }}" class="space-y-5">
 @csrf

 <div>
 <x-ui.input id="pet_name" name="pet_name" type="text" label="Pet Name" :value="old('pet_name')" placeholder="Milo"/>
 </div>

 <div>
 <x-ui.select
 id="pet_species"
 name="pet_species"
 label="Species"
 :options="collect(['' => 'Select species (optional)'])->merge(collect($speciesOptions)->mapWithKeys(fn ($species) => [$species => $species]))->all()"
 :selected="old('pet_species')"
 />
 </div>

 <div>
 <x-ui.textarea id="pet_bio" name="pet_bio" rows="4" label="Short Bio" placeholder="Friendly, playful, and loves park walks." :value="old('pet_bio')"/>
 </div>

 <div class="flex flex-wrap items-center justify-between gap-3">
 <x-ui.button type="submit" variant="primary">Save and Continue</x-ui.button>
 <x-ui.button type="submit" form="skip-step-2" variant="ghost">Skip this step</x-ui.button>
 </x-ui.card>
 </form>

 <form id="skip-step-2" method="POST" action="{{ route('onboarding.skip', ['step'=> 2]) }}">
 @csrf
 </form>
 </div>
</x-app-layout>
