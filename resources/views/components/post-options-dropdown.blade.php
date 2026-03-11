@props(['post','isOwn'=> false])

<div x-data="{ open: false, confirmDelete: false, copied: false }" class="relative">
 <button
 type="button"
 class="rounded-md p-1 text-gray-500 hover:bg-gray-100"
 @click="open = !open"
 aria-label="Post options"
 aria-haspopup="menu"
 :aria-expanded="open.toString()"
 >
 ⋯
 </button>

 <div x-show="open" @click.outside="open = false" class="absolute right-0 z-20 mt-2 w-44 rounded-lg border border-gray-200 bg-white p-1 shadow" style="display: none;" role="menu">
 @if ($isOwn)
 <a href="{{ route('posts.edit', $post) }}" class="block rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">✏️ Edit post</a>
 <button
 type="button"
 class="block w-full rounded-md px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
 @click="
 fetch('{{ $post->is_pinned ? route('posts.unpin', $post) : route('posts.pin', $post) }}', {
 method:'{{ $post->is_pinned ?'DELETE':'POST'}}',
 headers: {
'X-CSRF-TOKEN':'{{ csrf_token() }}',
'Accept':'application/json',
 }
 }).finally(() => window.location.reload());
"
 >
 {{ $post->is_pinned ?'📌 Unpin from profile':'📌 Pin to profile'}}
 </button>
 @endif

 <button type="button" class="block w-full rounded-md px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" @click="navigator.clipboard.writeText('{{ route('posts.show', $post) }}'); copied = true; setTimeout(() => copied = false, 2000);">🔗 <span x-text="copied ?'Copied!':'Copy link'"></span></button>

 @if ($isOwn)
 <div class="my-1 border-t border-gray-100"></div>
 <button type="button" class="block w-full rounded-md px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50" @click="confirmDelete = true">🗑️ Delete post</button>
 @endif
 </div>

 @if ($isOwn)
 <div x-show="confirmDelete" class="absolute right-0 z-30 mt-2 w-60 rounded-lg border border-red-200 bg-white p-3 shadow" style="display: none;">
 <p class="text-sm text-gray-700">Delete this post? This cannot be undone.</p>
 <div class="mt-3 flex justify-end gap-2">
 <button type="button" class="rounded-md px-3 py-1 text-sm text-gray-600 hover:bg-gray-100" @click="confirmDelete = false">Cancel</button>
 <button
 type="button"
 class="rounded-md bg-red-600 px-3 py-1 text-sm text-white"
 @click="
 fetch('{{ route('posts.destroy', $post) }}', {
 method:'DELETE',
 headers: {
'X-CSRF-TOKEN':'{{ csrf_token() }}',
'Accept':'application/json',
 }
 }).finally(() => $el.closest('article').remove());
"
 >Delete</button>
 </div>
 </div>
 @endif
</div>
