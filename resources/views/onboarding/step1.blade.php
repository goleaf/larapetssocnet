<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Onboarding: Step 1 of 3" description="Choose what interests you most so we can tailor your feed." icon="🧭" />
 </x-slot>

 <x-ui.card padding="lg">
 <form method="POST" action="{{ route('onboarding.store', ['step'=> 1]) }}" class="space-y-6">
 @csrf

 <div class="grid gap-3 sm:grid-cols-2">
 @foreach ($speciesOptions as $species)
 <label class="flex items-center gap-3 rounded-[var(--radius-soft)] border ui-border bg-[color:var(--ui-surface)] px-4 py-3">
 <input
 type="checkbox"
 name="interests[]"
 value="{{ $species }}"
 @checked(in_array($species, $selectedInterests, true))
 class="h-4 w-4 rounded-full border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
 >
 <span class="font-semibold">{{ $species }}</span>
 </label>
 @endforeach
 </div>

 <x-ui.hint :error="$errors->first('interests')" />
 <x-ui.hint :error="$errors->first('interests.*')" />

 <div class="flex flex-wrap items-center justify-between gap-3">
 <x-ui.button type="submit" variant="primary">Save and Continue</x-ui.button>
 <x-ui.button type="submit" form="skip-step-1" variant="ghost">Skip this step</x-ui.button>
 </x-ui.card>
 </form>

 <form id="skip-step-1" method="POST" action="{{ route('onboarding.skip', ['step'=> 1]) }}">
 @csrf
 </form>
 </div>
</x-app-layout>
