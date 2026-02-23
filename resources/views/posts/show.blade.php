<x-app-layout>
 <div class="max-w-4xl mx-auto py-8">
 <x-post-card :post="$post"/>

 @php
 $taggedPets = collect($post->tagged_pets ?? [])
 ->filter()
 ->whenNotEmpty(fn($ids) => auth()->user()?->pets()->whereIn('id', $ids)->get(), fn() => collect());
 @endphp

 @if ($taggedPets->isNotEmpty())
 <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4">
 <h3 class="mb-2 text-sm font-semibold text-gray-800">Tagged Pets</h3>
 <div class="flex flex-wrap gap-2">
 @foreach ($taggedPets as $pet)
 <a href="{{ route('pets.show', $pet->slug ?? $pet->getKey()) }}"
 class="rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-700 hover:bg-emerald-100">
 {{ $pet->name }}
 </a>
 @endforeach
 </div>
 </div>
 @endif

 <!-- Comments Section -->
 <x-ui.card class="mt-6 border-0 shadow-sm"id="comments">
 <x-slot name="header">
 <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
 <h3 class="font-bold text-gray-900 text-lg">Comments <span
 class="text-gray-500 font-normal text-base ml-1">({{ $post->comments_count }})</span></h3>
 </div>
 </x-slot>

 <div class="p-5">
 @auth
 <!-- Add Top-Level Comment Form -->
 <div class="mb-6 flex gap-3 items-start">
 <x-ui.avatar :src="auth()->user()->avatar_url":name="auth()->user()->name"size="sm"
 class="mt-1"/>
 <div class="flex-1">
 <form action="{{ route('posts.comments.store', $post) }}"method="POST"class="relative">
 @csrf
 <textarea name="body"rows="1"
 class="w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-2.5 pr-12 text-sm text-gray-900 placeholder-gray-500 focus:bg-white focus:border-paw focus:ring-1 focus:ring-paw resize-none overflow-hidden"
 placeholder="Write a comment..."
 oninput="this.style.height =''; this.style.height = this.scrollHeight +'px'"
 required></textarea>
 <button type="submit"
 class="absolute right-2 bottom-2 p-1.5 text-paw hover:bg-paw-light/30 rounded-full transition-colors disabled:opacity-50">
 <svg xmlns="http://www.w3.org/2000/svg"viewBox="0 0 24 24"fill="currentColor"
 class="w-5 h-5">
 <path
 d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z"/>
 </svg>
 </button>
 </form>
 </div>
 </div>
 @endauth

 <!-- Comments List -->
 @php
 $comments = $post->comments()->topLevel()->with(['user','replies.user'])->latest()->get();
 @endphp

 @if($comments->isEmpty())
 <div class="py-8 text-center">
 <p class="text-gray-500 text-sm">No comments yet. Be the first to share your thoughts!</p>
 </div>
 @else
 <div class="space-y-4">
 @foreach($comments as $comment)
 <x-comment-item :comment="$comment":post="$post"/>
 @endforeach
 </div>
 @endif
 </div>
 </x-ui.card>
 </div>
</x-app-layout>