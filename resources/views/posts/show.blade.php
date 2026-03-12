<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Post Details" :description="'Comments ('.$post->comments_count.')'" icon="📝" />
 </x-slot>

 <div class="mx-auto max-w-4xl space-y-5 py-8">
 <x-post-card :post="$post"/>

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

 <x-ui.card id="comments" padding="none">
 <x-slot name="header">
 <x-ui.card-header title="Comments" :subtitle="'('.$post->comments_count.')'"/>
 </x-slot>

 <div class="p-5">
 @auth
 <div class="mb-6 flex items-start gap-3">
 <x-ui.avatar :src="auth()->user()->avatar_url" :name="auth()->user()->name" size="sm" class="mt-1"/>
 <div class="flex-1">
 <form action="{{ route('posts.comments.store', $post) }}" method="POST" class="space-y-3">
 @csrf
 <x-ui.textarea
 name="body"
 rows="2"
 placeholder="Write a comment..."
 oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"
 required
 />
 <div class="flex justify-end">
 <x-ui.button type="submit" size="sm" variant="primary">Post Comment</x-ui.button>
 </div>
 </form>
 </div>
 </div>
 @endauth

 @if($comments->isEmpty())
 <x-ui.empty-state title="No comments yet" description="Be the first to share your thoughts!" icon="💬"/>
 @else
 <div class="space-y-4">
 @foreach($comments as $comment)
 <x-comment-item :comment="$comment" :post="$post"/>
 @endforeach
 </div>
 <div class="mt-4">
 {{ $comments->links() }}
 </div>
 @endif
 </div>
 </x-ui.card>
 </div>
</x-app-layout>
