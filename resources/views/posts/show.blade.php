<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Post Details" :description="'Comments ('.$post->comments_count.')'" icon="📝" />
 </x-slot>

 <div class="mx-auto max-w-4xl space-y-5 py-8">
 <x-post-card :post="$post" context="detail"/>

 @if ($taggedPets->isNotEmpty())
 <x-ui.card>
 <h3 class="text-sm font-semibold text-bark">Tagged Pets</h3>
 <div class="mt-3 flex flex-wrap gap-2">
 @foreach ($taggedPets as $pet)
 <x-ui.badge variant="primary" size="sm">
 <a href="{{ route('pets.show', $pet->slug ?? $pet->getKey()) }}">{{ $pet->name }}</a>
 </x-ui.badge>
 @endforeach
 </div>
 </x-ui.card>
 @endif

 <div id="comments">
 <livewire:posts.comments-thread :post="$post" :full-page="true" :key="'post-page-comments-'.$post->getKey()" />
 </div>
 </div>
</x-app-layout>
