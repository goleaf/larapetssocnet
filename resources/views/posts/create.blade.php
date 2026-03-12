<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Create Post" description="Share an update, photo, or story with the community." icon="✍️" />
 </x-slot>

 <div class="mx-auto max-w-3xl">
 <x-ui.card>
 <x-post-form :available-pets="$availablePets"/>
 </x-ui.card>
 </div>
</x-app-layout>
