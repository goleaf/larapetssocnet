<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Create Post" description="Share an update, photo, or story with the community." icon="✍️" />
 </x-slot>

 <div class="w-full">
 <livewire:posts.composer mode="inline" context-type="post-create" />
 </div>
</x-app-layout>
