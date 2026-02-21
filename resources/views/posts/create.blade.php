<x-app-layout>
    <x-slot name="header">
        <h2 class="shell-title text-xl">Create Post</h2>
    </x-slot>

    <x-post-form :post="$post" :pets="$pets" :action="route('posts.store')" method="POST" />
</x-app-layout>
