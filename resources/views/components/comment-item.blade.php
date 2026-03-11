@props(['comment','post'])

@php
 $currentReaction = auth()->check() ? $comment->reactions->where('user_id', auth()->id())->first()?->type : null;
 $reactionCounts = collect([
'like'=> $comment->reactions->where('type','like')->count(),
'love'=> $comment->reactions->where('type','love')->count(),
'laugh'=> $comment->reactions->where('type','laugh')->count(),
'wow'=> $comment->reactions->where('type','wow')->count(),
'sad'=> $comment->reactions->where('type','sad')->count(),
 ])->filter(fn($count) => $count > 0)->sortDesc();

 $reactionEmojis = [
'like'=>'👍',
'love'=>'❤️',
'laugh'=>'😆',
'wow'=>'😮',
'sad'=>'😢',
 ];
@endphp

<div class="group py-2 {{ $comment->isReply() ?'ml-11 mt-1':'mt-4'}}" id="comment-{{ $comment->id }}">
 <div class="flex items-start gap-2">
 <!-- Avatar -->
 <a href="{{ route('profile.show', $comment->user->username) }}" class="shrink-0 mt-0.5">
 <x-ui.avatar :src="$comment->user->avatar_url" :name="$comment->user->name" size="sm" />
 </a>

 <div class="flex-1 min-w-0" x-data="{ showReply: false, editing: false }">

 <!-- Comment Bubble -->
 <div x-show="!editing">
 <div class="flex items-center gap-2">
 <div class="inline-block bg-gray-100/80 rounded-2xl px-3.5 py-2.5 max-w-[85%]">
 <a href="{{ route('profile.show', $comment->user->username) }}"
 class="font-bold text-sm text-gray-400 hover:underline">
 {{ $comment->user->name }}
 </a>
 <div class="text-sm text-gray-400 whitespace-pre-wrap break-words mt-0.5 leading-snug">
 {{ $comment->body }}
 </div>
 </div>

 @if($comment->reactions_count > 0)
 <div class="flex items-center gap-1 flex-wrap">
 @foreach($reactionCounts as $type => $count)
 <div class="inline-flex items-center justify-center bg-white shadow-sm border border-gray-100/50 rounded-lg px-1.5 py-0.5"
 title="{{ ucfirst($type) }}">
 <span class="text-xs">{{ $reactionEmojis[$type] ??'👍'}}</span>
 <span class="text-[0.65rem] font-medium text-gray-400 ml-1">{{ $count }}</span>
 </div>
 @endforeach
 </div>
 @endif
 </div>
 </div>

 <!-- Edit Form -->
 @if(auth()->check() && auth()->id() === $comment->user_id)
 <div x-show="editing"x-cloak class="w-full max-w-2xl bg-gray-50 rounded-2xl p-2 border border-gray-200">
 <form action="{{ route('posts.comments.update', ['post'=> $post,'comment'=> $comment]) }}"
 method="POST">
 @csrf @method('PATCH')
 <textarea name="body" rows="2"
 class="w-full bg-transparent border-0 focus:ring-0 p-1 text-sm resize-none"
 required>{{ $comment->body }}</textarea>
 <div class="mt-2 flex justify-end gap-2 pr-1 pb-1">
 <button type="button" @click="editing = false"
 class="text-xs font-semibold text-gray-400 hover:text-gray-400">Cancel</button>
 <button type="submit"
 class="text-xs font-bold text-paw hover:text-paw-dark drop-shadow-sm">Update</button>
 </div>
 </form>
 </div>
 @endif

 <!-- Action Links -->
 <div x-show="!editing" class="flex items-center gap-3 px-3 mt-1 text-xs font-bold text-gray-400">
 @auth
 <!-- Like Button / Reactions -->
 <x-comment-reaction-bar :post="$post" :comment="$comment" :currentReaction="$currentReaction" />

 <!-- Reply Button -->
 @if(!$comment->isReply())
 <button @click="showReply = !showReply; if(showReply) { $nextTick(() => $refs.replyInput.focus()); }"
 class="hover:underline">Reply</button>
 @endif
 @endauth

 <!-- Timestamp -->
 <span class="font-normal text-gray-400 hover:underline cursor-pointer"
 title="{{ $comment->created_at->format('M j, Y g:i A') }}">
 {{ str_replace(['seconds','minutes','hours','days','weeks','months','years','ago'], ['s','m','h','d','w','mo','y',''], $comment->created_at->diffForHumans()) }}
 </span>

 <!-- Hover Actions (Edit/Delete/Report) -->
 <div class="opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-2">
 <span class="text-gray-300 font-normal">&middot;</span>
 @if(auth()->check() && (auth()->id() === $comment->user_id || auth()->user()->hasRole('admin')))
 <button @click="editing = true" class="hover:underline hover:text-gray-400">Edit</button>
 <form method="POST"
 action="{{ route('posts.comments.destroy', ['post'=> $post,'comment'=> $comment]) }}"
 class="inline m-0 p-0" onsubmit="return confirm('Delete this comment?');">
 @csrf @method('DELETE')
 <button type="submit" class="hover:underline hover:text-red-500">Delete</button>
 </form>
 @endif
 @if(auth()->check() && auth()->id() !== $comment->user_id)
 <form method="POST"
 action="{{ route('comments.report', ['post'=> $post,'comment'=> $comment]) }}"
 class="inline m-0 p-0" onsubmit="return confirm('Report this comment?');">
 @csrf
 <input type="hidden" name="reason" value="spam">
 <button type="submit" class="hover:underline hover:text-amber-600">Report</button>
 </form>
 @endif
 </div>
 </div>

 <!-- Inline Reply Form -->
 <div x-show="showReply"x-cloak class="mt-2 w-full max-w-2xl flex items-start gap-2">
 <x-ui.avatar :src="auth()->user()?->avatar_url" :name="auth()->user()?->name" size="xs" class="mt-1" />
 <div class="flex-1">
 <form action="{{ route('posts.comments.store', $post) }}" method="POST" class="relative">
 @csrf
 <input type="hidden" name="parent_id" value="{{ $comment->id }}">
 <textarea x-ref="replyInput" name="body" rows="1"
 class="w-full py-2 pl-3 pr-10 text-sm bg-gray-100 border-transparent rounded-2xl focus:bg-white focus:border-paw focus:ring-1 focus:ring-paw resize-none overflow-hidden"
 placeholder="Write a reply..."required
 oninput="this.style.height =''; this.style.height = this.scrollHeight +'px'"
 @keydown.escape="showReply = false"></textarea>
 <button type="submit"
 class="absolute right-2 bottom-1.5 p-1 text-paw hover:bg-paw-light/30 rounded-full transition-colors">
 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
 class="w-4 h-4">
 <path
 d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
 </svg>
 </button>
 </form>
 </div>
 </div>

 <!-- Children / Replies -->
 @if($comment->replies->count() > 0)
 <div class="mt-1">
 @foreach($comment->replies as $reply)
 <x-comment-item :comment="$reply" :post="$post" />
 @endforeach
 </div>
 @endif
 </div>
 </div>
</div>