<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Onboarding: Step 2 of 3" description="Create your first pet profile now or skip and do it later." icon="🐾" />
 </x-slot>

 <x-ui.card padding="lg">
 <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
 <div class="space-y-4">
 <p class="shell-kicker">Start their story</p>
 <h2 class="font-display text-2xl font-semibold text-bark">Give your pet their own identity on PetSocial</h2>
 <p class="max-w-2xl text-sm leading-6 shell-text-muted">
 Pet profiles have their own followers, posts, photos, milestones, and care history. The same creation wizard is used everywhere in the app, so you can launch a pet profile without leaving onboarding.
 </p>
 <div class="flex flex-col gap-2 sm:flex-row">
 <x-ui.button
 type="button"
 variant="primary"
 size="lg"
 aria-haspopup="dialog"
 aria-controls="pet-create-wizard"
 @click="window.openPetCreateWizard('onboarding-step-2')">
 Add your first pet
 </x-ui.button>
 <x-ui.button type="submit" form="skip-step-2" variant="ghost" size="lg">
 Skip this step
 </x-ui.button>
 </div>
 </div>

 <div class="ui-panel p-4">
 <p class="text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">What opens</p>
 <ul class="mt-3 space-y-2 text-sm leading-6 shell-text-muted">
 <li>• Four-step pet profile wizard</li>
 <li>• Breed autocomplete and personality traits</li>
 <li>• Privacy controls and profile preview</li>
 <li>• Optional adoption listing starter</li>
 </ul>
 </div>
 </div>

 <form id="skip-step-2" method="POST" action="{{ route('onboarding.skip', ['step' => 2]) }}">
 @csrf
 </form>
 </x-ui.card>
</x-app-layout>
