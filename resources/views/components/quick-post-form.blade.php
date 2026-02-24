<div class="rounded-2xl border border-gray-200 bg-white p-3"x-data="{ expanded: false }">
 <div class="flex items-center gap-3">
 <x-avatar :src="auth()->user()?->avatar_url" :name="auth()->user()?->name" size="md"/>
 <button type="button" class="w-full rounded-full border border-gray-200 px-4 py-2 text-left text-sm text-gray-500"@click="expanded = true">What's on your mind?</button>
 </div>

 <div x-show="expanded" class="mt-3 space-y-3"style="display: none;">
 <textarea class="w-full rounded-lg border-gray-300 text-sm"rows="3" placeholder="Share a pet update..."></textarea>
 <div class="flex items-center justify-between">
 <a href="{{ route('posts.create') }}" class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white">Post</a>
 <button type="button" class="text-sm text-gray-500 hover:text-gray-700"@click="expanded = false">Cancel</button>
 </div>
 </div>
</div>
