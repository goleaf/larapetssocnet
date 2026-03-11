@props(['user'])

<div class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center">
 @if (($user->following_count ?? 0) === 0)
 <p class="text-3xl">🐾</p>
 <h3 class="mt-2 text-lg font-semibold text-gray-400">Your feed is empty</h3>
 <p class="mt-1 text-sm text-gray-400">Follow some pet lovers to see their posts here.</p>
 <div class="mt-4 flex flex-wrap justify-center gap-2">
 <a href="{{ route('explore.index', ['tab'=>'users']) }}" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white">Explore users</a>
 <a href="{{ route('explore.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-400">Browse public posts</a>
 </div>
 @else
 <h3 class="text-lg font-semibold text-gray-400">Nothing new right now</h3>
 <p class="mt-1 text-sm text-gray-400">The people you follow haven't posted yet.</p>
 <a href="{{ route('explore.index') }}" class="mt-4 inline-flex rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-400">Explore</a>
 @endif
</div>
