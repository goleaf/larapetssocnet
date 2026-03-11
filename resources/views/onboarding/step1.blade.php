<x-app-layout>
 <x-slot name="header">
 <div>
 <h1 class="shell-title text-xl">{{ __('en.onboarding_step_1_of_3') }}</h1>
 <p class="mt-1 text-sm shell-text-muted">{{ __('en.choose_what_interests_you_most_so_we_can_tailor_your_feed') }}</p>
 </div>
 </x-slot>

 <div class="shell-card p-6 sm:p-8">
 <form method="POST" action="{{ route('onboarding.store', ['step'=> 1]) }}" class="space-y-6">
 @csrf

 <div class="grid gap-3 sm:grid-cols-2">
 @foreach ($speciesOptions as $species)
 <label class="flex items-center gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3">
 <input
 type="checkbox"
 name="interests[]"
 value="{{ $species }}"
 @checked(in_array($species, $selectedInterests, true))
 class="h-4 w-4 rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
 >
 <span class="font-semibold">{{ $species }}</span>
 </label>
 @endforeach
 </div>

 <x-input-error :messages="$errors->get('interests')" class="mt-2" />
 <x-input-error :messages="$errors->get('interests.*')" class="mt-2" />

 <div class="flex flex-wrap items-center justify-between gap-3">
 <button type="submit" class="btn-base btn-primary">{{ __('en.save_and_continue') }}</button>
 <button
 type="submit"
 form="skip-step-1"
 class="btn-base btn-ghost"
 >
 {{ __('en.skip_this_step') }}
 </button>
 </div>
 </form>

 <form id="skip-step-1" method="POST" action="{{ route('onboarding.skip', ['step'=> 1]) }}">
 @csrf
 </form>
 </div>
</x-app-layout>
