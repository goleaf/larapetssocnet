@props([
    'comment',
    'post',
    'livewire' => false,
    'editingCommentId' => null,
    'mentionTarget' => null,
    'mentionSuggestions' => [],
    'mentionSuggestionsOpen' => false,
    'expandedReplyIds' => [],
    'translations' => [],
    'searchQuery' => '',
])

@php($replyErrorKey = 'replyBodies.'.$comment->id)
@php($editErrorKey = 'editBodies.'.$comment->id)
@php($isLivewireEditing = $livewire && (int) ($editingCommentId ?? 0) === (int) $comment->id)
@php($threadDepth = (int) ($comment->thread_depth ?? ($comment->isReply() ? 2 : 1)))
@php($loadedReplyCount = $comment->relationLoaded('replies') ? $comment->replies->count() : 0)
@php($isReplyExpanded = (bool) ($expandedReplyIds[$comment->id] ?? false))
@php($reactionReactors = $comment->reaction_reactors ?? [])
@php($translatedBody = $translations[$comment->id] ?? null)

<div class="group/comment py-2 {{ $comment->isReply() ? 'relative ml-8 border-l border-whisker/35 pl-3 mt-1 sm:ml-11' : 'mt-4' }}" id="comment-{{ $comment->id }}">
 <div class="flex items-start gap-2">
 <!-- Avatar -->
 <a href="{{ route('profile.show', $comment->user->username) }}" class="shrink-0 mt-0.5">
 <x-ui.avatar :src="$comment->user->avatar_url" :name="$comment->user->name" :user="$comment->user" size="sm"/>
 </a>

 <div class="flex-1 min-w-0" x-data="{
 showReply: false,
 editing: false,
 collapsed: false,
 deleteConfirm: false,
 detectMention(value, target) {
 const match = value.match(/(?:^|\s)@([A-Za-z0-9-]{1,30})$/)

 if (match) {
 $wire.searchMentionSuggestions(match[1], target)
 return
 }

 $wire.closeMentionSuggestions()
 },
 }">

 <!-- Comment Bubble -->
 <div @if(! $livewire) x-show="!editing" @endif @if($isLivewireEditing) class="hidden" @endif>
 <div class="flex items-center gap-2">
 <div class="inline-block max-w-[85%] border border-whisker/30 bg-cream/60 px-3.5 py-2.5">
 <a href="{{ route('profile.show', $comment->user->username) }}"
 class="font-bold text-sm text-gray-900 hover:underline">
 {{ $comment->user->name }}
 </a>

 @if($comment->trashed())
 <div class="text-sm text-gray-500 italic mt-0.5 leading-snug">This comment was removed.</div>
 @else
 @if(filled($comment->body))
 <div class="text-sm text-gray-800 whitespace-pre-wrap break-words mt-0.5 leading-snug">
 {!! $comment->highlighted_body_html ?? $comment->body_html !!}
 </div>
 @endif
 @if(filled($comment->gif_url))
 <div class="mt-2 overflow-hidden rounded-[var(--radius-soft)] border border-whisker/35 bg-warm-white">
 <img src="{{ $comment->gif_preview_url ?: $comment->gif_url }}" data-full-gif="{{ $comment->gif_url }}" alt="{{ $comment->gif_title ?: 'Comment GIF' }}" class="max-h-64 w-full max-w-sm object-cover" loading="lazy">
 </div>
 @endif
 @if($translatedBody)
 <div class="mt-2 border-l-2 border-paw/40 pl-3 text-sm text-fur">
 {{ $translatedBody }}
 </div>
 @endif
 @if($comment->edited_at)
 <div class="mt-1 text-[0.65rem] uppercase tracking-wide text-gray-400">Edited</div>
 @endif
 @endif
 </div>

 </div>
 @if($reactionReactors !== [])
 <div class="mt-1 flex items-center gap-2">
 <div class="flex -space-x-2">
 @foreach($reactionReactors as $reactor)
 <a href="{{ route('profile.show', $reactor['username']) }}" class="inline-flex rounded-full ring-2 ring-warm-white" title="{{ $reactor['name'] }} reacted with {{ $reactor['type'] }}">
 <x-ui.avatar :src="$reactor['avatar_url']" :name="$reactor['name']" size="xs"/>
 </a>
 @endforeach
 </div>
 <span class="text-[0.7rem] font-semibold text-fur">{{ trans_choice(':count reaction|:count reactions', (int) $comment->reactions_count, ['count' => (int) $comment->reactions_count]) }}</span>
 </div>
 @endif
 @if(! $comment->trashed() && ($comment->should_translate ?? false))
 <div class="mt-1">
 <button type="button" wire:click="translateComment({{ (int) $comment->id }})" wire:loading.attr="disabled" wire:target="translateComment({{ (int) $comment->id }})" class="text-xs font-semibold text-paw hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 {{ $translatedBody ? 'Refresh translation' : 'Translate' }}
 </button>
 </div>
 @endif
 </div>

 <!-- Edit Form -->
 @can('update', $comment)
 @if($livewire)
 @if($isLivewireEditing)
 <div class="w-full max-w-2xl border border-whisker/30 bg-cream/60 p-2">
 <form wire:submit.prevent="updateComment({{ (int) $comment->id }})">
 <textarea wire:model.live.debounce.300ms="editBodies.{{ (int) $comment->id }}" rows="2" maxlength="{{ \App\Services\CommentService::MAX_BODY_LENGTH }}"
 class="form-textarea w-full border-0 bg-transparent p-1 text-sm resize-none focus:ring-0"
 required
 x-on:input.debounce.250ms="detectMention($event.target.value, 'edit:{{ (int) $comment->id }}')"
 x-on:keydown.enter.exact.prevent="$wire.updateComment({{ (int) $comment->id }})"
 x-on:keydown.escape="$wire.cancelEditing()"></textarea>
 @if($mentionSuggestionsOpen && $mentionTarget === 'edit:'.$comment->id && $mentionSuggestions !== [])
 <div class="mb-2 overflow-hidden rounded-[var(--radius-soft)] border border-whisker/35 bg-warm-white shadow-card" role="listbox" aria-label="Mention suggestions">
 @foreach($mentionSuggestions as $suggestion)
 <button type="button" wire:click="insertMention('{{ $suggestion['username'] }}')" class="flex w-full items-center gap-3 px-3 py-2 text-left transition hover:bg-cream focus:bg-cream focus:outline-none" role="option">
 <x-ui.avatar :src="$suggestion['avatar_url']" :name="$suggestion['name']" size="sm"/>
 <span class="min-w-0">
 <span class="block truncate text-sm font-semibold text-bark">{{ $suggestion['name'] }}</span>
 <span class="block truncate text-xs text-fur">&#64;{{ $suggestion['username'] }}</span>
 </span>
 </button>
 @endforeach
 </div>
 @endif
 @error($editErrorKey)
 <p class="mt-1 px-1 text-xs font-semibold text-rose">{{ $message }}</p>
 @enderror
 <div class="mt-2 flex justify-end gap-2 pr-1 pb-1">
 <button type="button" wire:click="cancelEditing"
 class="text-xs font-semibold text-gray-500 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Cancel</button>
 <button type="submit"
 wire:loading.attr="disabled"
 wire:target="updateComment"
 class="text-xs font-bold text-paw hover:text-paw-dark drop-shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Update</button>
 </div>
 </form>
 </div>
 @endif
 @else
 <div x-show="editing" x-cloak class="w-full max-w-2xl border border-whisker/30 bg-cream/60 p-2">
 <form action="{{ route('posts.comments.update', ['post'=> $post,'comment'=> $comment]) }}"
 method="POST">
 @csrf @method('PATCH')
 <textarea name="body" rows="2"
 class="form-textarea w-full border-0 bg-transparent p-1 text-sm resize-none focus:ring-0"
 required>{{ $comment->body }}</textarea>
 <div class="mt-2 flex justify-end gap-2 pr-1 pb-1">
 <button type="button" @click="editing = false"
 class="text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</button>
 <button type="submit"
 class="text-xs font-bold text-paw hover:text-paw-dark drop-shadow-sm">Update</button>
 </div>
 </form>
 </div>
 @endif
 @endcan

 <!-- Action Links -->
 <div @if(! $livewire) x-show="!editing" @endif @if($isLivewireEditing) class="hidden" @else class="flex items-center gap-3 px-3 mt-1 text-xs font-bold text-gray-500" @endif>
@auth
@if(! $comment->trashed())
<!-- Like Button / Reactions -->
@can('react', $comment)
<x-comment-reaction-bar :post="$post" :comment="$comment" :currentReaction="$comment->current_viewer_reaction"/>
@endcan
@endif

 <!-- Reply Button -->
 @can('reply', $comment)
 @if(! $comment->trashed())
 <button
 @if($livewire)
 wire:click="startReply({{ (int) $comment->id }}, '{{ $comment->user->username }}')"
 @endif
 @click="showReply = !showReply; if(showReply) { $nextTick(() => $refs.replyInput.focus()); }"
 class="hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Reply</button>
 @endif
 @endcan
 @endauth

 <!-- Timestamp -->
 <span class="font-normal text-gray-400 hover:underline cursor-pointer"
 title="{{ $comment->created_at->format('M j, Y g:i A') }}">
 {{ str_replace(['seconds','minutes','hours','days','weeks','months','years','ago'], ['s','m','h','d','w','mo','y',''], $comment->created_at->diffForHumans()) }}
 </span>

 <!-- Hover Actions (Edit/Delete/Report) -->
 <div class="flex items-center gap-2 opacity-100 transition-opacity sm:opacity-0 sm:group-hover/comment:opacity-100">
 <span class="text-gray-300 font-normal">&middot;</span>
 @can('update', $comment)
 @if($livewire)
 <button type="button" wire:click="startEditing({{ (int) $comment->id }})" class="hover:underline hover:text-gray-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Edit</button>
 @else
 <button @click="editing = true" class="hover:underline hover:text-gray-800">Edit</button>
 @endif
 @endcan
 @can('delete', $comment)
 @if($livewire)
 <button type="button" @click="deleteConfirm = ! deleteConfirm" class="hover:underline hover:text-red-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Delete</button>
 @else
 <form method="POST"
 action="{{ route('posts.comments.destroy', ['post'=> $post,'comment'=> $comment]) }}"
 class="inline m-0 p-0" onsubmit="return confirm('Delete this comment?');">
 @csrf @method('DELETE')
 <button type="submit" class="hover:underline hover:text-red-500">Delete</button>
 </form>
 @endif
 @endcan
 @can('pin', $post)
 @if($livewire && ! $comment->trashed())
 @if((int) data_get($post->metadata ?? [], 'pinned_comment_id') === (int) $comment->id)
 <button type="button" wire:click="unpinComment" class="hover:underline hover:text-amber-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Unpin</button>
 @else
 <button type="button" wire:click="pinComment({{ (int) $comment->id }})" class="hover:underline hover:text-amber-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Pin</button>
 @endif
 @endif
 @endcan
@can('report', $comment)
@if(! $comment->trashed())
@if($livewire)
<button type="button" wire:click="openReport({{ (int) $comment->id }})" wire:loading.attr="disabled" wire:target="openReport" class="hover:underline hover:text-amber-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Report</button>
@else
<form method="POST"
 action="{{ route('comments.report', ['post'=> $post,'comment'=> $comment]) }}"
 class="inline m-0 p-0" onsubmit="return confirm('Report this comment?');">
 @csrf
 <input type="hidden" name="reason" value="spam">
 <button type="submit" class="hover:underline hover:text-amber-600">Report</button>
</form>
@endif
@endif
@endcan
@auth
@if($livewire && ! $comment->trashed() && (int) $comment->user_id !== (int) auth()->id())
<button type="button" wire:click="blockCommenter({{ (int) $comment->id }})" wire:loading.attr="disabled" wire:target="blockCommenter" class="hover:underline hover:text-rose focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Block user</button>
@endif
@if($livewire && ($comment->is_thread_subscribed ?? false))
<button type="button" wire:click="unsubscribeFromThread({{ (int) $comment->id }})" wire:loading.attr="disabled" wire:target="unsubscribeFromThread" class="hover:underline hover:text-amber-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Unsubscribe from thread</button>
@endif
@endauth
 </div>
 </div>

 @if($livewire)
 <div x-show="deleteConfirm" x-cloak x-transition.opacity.duration.150ms class="ml-3 mt-2 inline-flex items-center gap-2 rounded-[var(--radius-soft)] border border-rose/30 bg-rose-light/30 px-3 py-2 text-xs font-semibold text-bark">
 <span>Are you sure?</span>
 <button type="button" wire:click="deleteComment({{ (int) $comment->id }})" wire:loading.attr="disabled" wire:target="deleteComment" class="text-rose hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Delete</button>
 <button type="button" @click="deleteConfirm = false" class="text-fur hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Cancel</button>
 </div>
 @endif

 <!-- Inline Reply Form -->
 @can('reply', $comment)
 <div x-show="showReply" x-cloak class="mt-2 w-full max-w-2xl flex items-start gap-2">
 <x-ui.avatar :src="auth()->user()?->avatar_url" :name="auth()->user()?->name" :user="auth()->user()" size="xs" class="mt-1"/>
 <div class="flex-1">
 @if($livewire)
 <form wire:submit.prevent="createReply({{ (int) $comment->id }})" class="relative">
 <textarea x-ref="replyInput" wire:model.live.debounce.300ms="replyBodies.{{ (int) $comment->id }}" rows="1" maxlength="{{ \App\Services\CommentService::MAX_BODY_LENGTH }}"
 class="form-textarea w-full resize-none overflow-hidden py-2 pl-3 pr-10 text-sm"
 placeholder="Write a reply..." required
 oninput="this.style.height =''; this.style.height = this.scrollHeight +'px'"
 x-on:input.debounce.250ms="detectMention($event.target.value, 'reply:{{ (int) $comment->id }}')"
 x-on:keydown.enter.exact.prevent="$wire.createReply({{ (int) $comment->id }})"
 @keydown.escape="showReply = false"></textarea>
 <button type="submit"
 wire:loading.attr="disabled"
 wire:target="createReply"
 class="absolute bottom-1.5 right-2 p-1 text-paw transition-colors hover:bg-paw-light/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
 class="w-4 h-4">
 <path
 d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z"/>
 </svg>
 </button>
 </form>
 @if($mentionSuggestionsOpen && $mentionTarget === 'reply:'.$comment->id && $mentionSuggestions !== [])
 <div class="mt-2 overflow-hidden rounded-[var(--radius-soft)] border border-whisker/35 bg-warm-white shadow-card" role="listbox" aria-label="Mention suggestions">
 @foreach($mentionSuggestions as $suggestion)
 <button type="button" wire:click="insertMention('{{ $suggestion['username'] }}')" class="flex w-full items-center gap-3 px-3 py-2 text-left transition hover:bg-cream focus:bg-cream focus:outline-none" role="option">
 <x-ui.avatar :src="$suggestion['avatar_url']" :name="$suggestion['name']" size="sm"/>
 <span class="min-w-0">
 <span class="block truncate text-sm font-semibold text-bark">{{ $suggestion['name'] }}</span>
 <span class="block truncate text-xs text-fur">&#64;{{ $suggestion['username'] }}</span>
 </span>
 </button>
 @endforeach
 </div>
 @endif
 @error($replyErrorKey)
 <p class="mt-1 text-xs font-semibold text-rose">{{ $message }}</p>
 @enderror
 @else
 <form action="{{ route('posts.comments.store', $post) }}" method="POST" class="relative">
 @csrf
 <input type="hidden" name="parent_id" value="{{ $comment->id }}">
 <textarea x-ref="replyInput" name="body" rows="1"
 class="form-textarea w-full resize-none overflow-hidden py-2 pl-3 pr-10 text-sm"
 placeholder="Write a reply..." required
 oninput="this.style.height =''; this.style.height = this.scrollHeight +'px'"
 @keydown.escape="showReply = false"></textarea>
 <button type="submit"
 class="absolute bottom-1.5 right-2 p-1 text-paw transition-colors hover:bg-paw-light/30">
 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
 class="w-4 h-4">
 <path
 d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z"/>
 </svg>
 </button>
 </form>
 @endif
 </div>
 </div>
 @endcan

 <!-- Children / Replies -->
 @if($comment->replies_count > 0)
 <div class="mt-1">
 <div class="flex items-center gap-2 px-3 text-xs font-semibold text-gray-500">
 @if($livewire && $loadedReplyCount < (int) $comment->replies_count && ! $isReplyExpanded)
 <button type="button" class="hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" wire:click="toggleReplies({{ (int) $comment->id }})">
 View {{ (int) $comment->replies_count - $loadedReplyCount }} more {{ \Illuminate\Support\Str::plural('reply', (int) $comment->replies_count - $loadedReplyCount) }}
 </button>
 @else
 <button type="button" class="hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" @click="collapsed = !collapsed">
 <span x-show="!collapsed">{{ $isReplyExpanded ? 'Collapse replies' : 'Hide '.$comment->replies_count.' '.\Illuminate\Support\Str::plural('reply', (int) $comment->replies_count) }}</span>
 <span x-show="collapsed">Show {{ $comment->replies_count }} {{ \Illuminate\Support\Str::plural('reply', (int) $comment->replies_count) }}</span>
 </button>
 @if($livewire && $isReplyExpanded)
 <button type="button" class="hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" wire:click="toggleReplies({{ (int) $comment->id }})">Show fewer</button>
 @endif
 @endif
 </div>
 <div class="mt-2" x-show="!collapsed" x-cloak x-transition.opacity.duration.150ms>
 @foreach($comment->replies as $reply)
 <x-comment-item
 :comment="$reply"
 :post="$post"
 :livewire="$livewire"
 :editing-comment-id="$editingCommentId"
 :mention-target="$mentionTarget"
 :mention-suggestions="$mentionSuggestions"
 :mention-suggestions-open="$mentionSuggestionsOpen"
 :expanded-reply-ids="$expandedReplyIds"
 :translations="$translations"
 :search-query="$searchQuery"
 />
 @endforeach
 </div>
 </div>
 @endif
 </div>
 </div>
</div>
