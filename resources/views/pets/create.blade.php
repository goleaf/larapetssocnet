<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Create Pet Profile" description="Add a new pet and share their story with the community." icon="✨" />
 </x-slot>

 <x-ui.page-stack>
 <x-ui.card padding="lg">
 <form
 method="POST"
 action="{{ route('pets.store') }}"
 enctype="multipart/form-data"
 class="space-y-6"
 data-ui="pet-create-wizard"
 x-data="{ step: 1, maxStep: 3 }">
 @csrf

 <div class="grid gap-2 sm:grid-cols-3" aria-label="Pet creation steps">
 <button type="button" class="ui-list-item px-3 py-2 text-left text-sm font-semibold" :class="step === 1 ? 'border-paw text-paw' : 'text-fur'" @click="step = 1">Basics</button>
 <button type="button" class="ui-list-item px-3 py-2 text-left text-sm font-semibold" :class="step === 2 ? 'border-paw text-paw' : 'text-fur'" @click="step = 2">Story</button>
 <button type="button" class="ui-list-item px-3 py-2 text-left text-sm font-semibold" :class="step === 3 ? 'border-paw text-paw' : 'text-fur'" @click="step = 3">Photos</button>
 </div>

 @include('pets.partials.form', ['wizard' => true])

 <div class="flex flex-col-reverse gap-2 border-t border-whisker/40 pt-4 sm:flex-row sm:items-center sm:justify-between">
 <x-ui.button :href="route('pets.explore')" variant="ghost">Cancel</x-ui.button>
 <div class="flex gap-2">
 <x-ui.button type="button" variant="outline" x-show="step > 1" @click="step--">Back</x-ui.button>
 <x-ui.button type="button" variant="primary" x-show="step < maxStep" @click="step++">Next</x-ui.button>
 <x-ui.button type="submit" variant="primary" x-show="step === maxStep">Create profile</x-ui.button>
 </div>
 </div>
 </form>
 </x-ui.card>
 </x-ui.page-stack>
</x-app-layout>
