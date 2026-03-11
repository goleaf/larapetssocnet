@props([
'post',
'myReactions'=> collect(),
'mySaved'=> collect(),
'showComments'=> false,
'compact'=> false,
'context'=>'feed',
])

@php
 $currentReaction = $myReactions->get($post->id)?->type;
 $isSaved = $mySaved->has($post->id);
 $isOwn = auth()->id() === $post->user_id;
 $timeIso = $post->created_at?->toIso8601String();
 $timeDisplay = $post->created_at && $post->created_at->diffInDays(now()) < 7
 ? $post->created_at->diffForHumans()
 : $post->created_at?->format('M j, Y');
@endphp

<article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" aria-label="Post by {{ $post->author->name }}">
 @if ($post->is_pinned && $context ==='profile')
 <div class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs font-medium text-amber-700">Pinned post</div>
 @endif

 <header class="flex items-start justify-between p-4 pb-0">
 <div class="flex items-start gap-3">
 <a href="{{ route('profile.show', $post->author) }}">
 <x-avatar :src="$post->author->avatar_url" :name="$post->author->name" size="md"/>
 </a>
 <div>
 <a href="{{ route('profile.show', $post->author) }}" class="text-sm font-semibold text-gray-900 hover:underline">{{ $post->author->name }}</a>
 <div class="mt-0.5 flex items-center gap-2 text-xs text-gray-500">
 <a href="{{ route('profile.show', $post->author) }}" class="hover:underline">&#64;{{ $post->author->username }}</a>
 <span aria-hidden="true">·</span>
 <time datetime="{{ $timeIso }}" title="{{ $post->created_at?->format('M j Y g:ia') }}">{{ $timeDisplay }}</time>
 @if ($context ==='profile'&& auth()->id() === $post->user_id && $post->visibility !=='public')
 <x-visibility-badge :visibility="$post->visibility"/>
 @endif
 @if ($post->pet)
 <span aria-hidden="true">·</span>
 <a href="{{ route('pets.show', $post->pet->slug ?? $post->pet->getKey()) }}" class="text-emerald-600 hover:underline">🐾 {{ $post->pet->name }}</a>
 @endif
 </div>
 </div>
 </div>

 <x-post-options-dropdown :post="$post" :isOwn="$isOwn"/>
 </header>

 @if ($post->body_html || $post->body)
 <div class="px-4 py-3" x-data="{ expanded: false }">
 @php
 $renderedBody = $post->body ? e($post->body) : ($post->body_html ?:'');
 $plain = strip_tags($renderedBody);
 @endphp
 @if (strlen($plain) > 300)
 <div class="text-sm leading-relaxed text-gray-800" x-show="!expanded">{{ Str::limit($plain, 300) }}</div>
 <div class="prose prose-sm max-w-none text-sm text-gray-800" x-show="expanded">{!! $renderedBody !!}</div>
 <button type="button" @click="expanded = !expanded" class="mt-2 text-xs font-medium text-emerald-600 hover:underline" x-text="expanded ?'Read less':'Read more'"></button>
 @else
 <div class="prose prose-sm max-w-none text-sm text-gray-800">{!! $renderedBody !!}</div>
 @endif
 </div>
 @endif

 @if ($post->type ==='photo'&& $post->has_media)
 <x-media-grid :post="$post"/>
 @elseif ($post->type ==='video'&& $post->has_media)
 <x-video-player :post="$post"/>
 @endif

 @if ($post->location)
 <div class="px-4 py-1 text-xs text-gray-500">📍 {{ $post->location }}</div>
 @endif

 <div class="flex items-center gap-4 border-b border-gray-100 px-4 py-2 text-xs text-gray-500">
 @if ($post->reactions_count > 0)
 <span>❤️ {{ $post->reactions_count }} reactions</span>
 @endif
 @if ($post->comments_count > 0)
 <span>💬 {{ $post->comments_count }} comments</span>
 @endif
 </div>

 <div class="flex items-center justify-between px-3 py-2">
 <x-reaction-bar :post="$post" :currentReaction="$currentReaction"/>

 <div class="flex items-center gap-2 text-sm">
 <a href="{{ route('posts.show', $post) }}#comments" class="rounded-md px-2 py-1 text-gray-600 hover:bg-gray-100">💬 {{ $post->comments_count ?? 0 }} {{ $post->comments_count === 1 ?'Comment':'Comments'}}</a>

 <button
 type="button"
 class="rounded-md px-2 py-1 text-gray-600 hover:bg-gray-100"
 x-data="{ saved: {{ $isSaved ?'true':'false'}} }"
 @click="
 const previous = saved;
 saved = !saved;
 fetch('{{ route('posts.save', $post) }}', {
 method:'POST',
 headers: {
'X-CSRF-TOKEN':'{{ csrf_token() }}',
'Accept':'application/json',
 }
 })
 .then(r => r.json())
 .then(data => { if (!data.success) { saved = previous; } })
 .catch(() => { saved = previous; });
"
 :aria-label="saved ?'Unsave post':'Save post'"
 >
 <span x-text="saved ?'🔖':'📑'"></span>
 </button>

 <button
 type="button"
 class="rounded-md px-2 py-1 text-gray-600 hover:bg-gray-100"
 x-data="{ copied: false }"
 @click="
 navigator.clipboard.writeText('{{ route('posts.show', $post) }}')
 .then(() => { copied = true; setTimeout(() => copied = false, 2000); });
"
 :aria-label="copied ?'Copied post link':'Copy post link'"
 >
 <span x-text="copied ?'Copied!':'Share'"></span>
 </button>
 </div>
 </div>
</article>
