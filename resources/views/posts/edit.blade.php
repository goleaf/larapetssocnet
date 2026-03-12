<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Edit Post" description="Update your post details and media." icon="🛠️" />
 </x-slot>

 <div class="mx-auto max-w-3xl">
 <x-ui.card>
 <x-post-form :post="$post" :available-pets="$availablePets"/>
 </x-ui.card>
 </div>
</x-app-layout>
