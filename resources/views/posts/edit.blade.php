<x-app-layout>
    <x-slot name="header">
        <h2 class="shell-title text-xl">Edit Post</h2>
    </x-slot>

    <x-post-form :post="$post" :pets="$pets" :action="route('posts.update', $post)" method="PATCH" />
</x-app-layout>
