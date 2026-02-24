@props(['suggestions'])

<section class="rounded-2xl border border-gray-200 bg-white p-4">
 <h3 class="text-sm font-semibold text-gray-900">Who to Follow</h3>
 <ul class="mt-3 space-y-3">
 @foreach ($suggestions as $suggestion)
 <li class="flex items-center justify-between gap-2">
 <a href="{{ route('profile.show', $suggestion) }}" class="flex min-w-0 items-center gap-2">
 <x-avatar :src="$suggestion->avatar_url" :name="$suggestion->name" size="sm"/>
 <span class="min-w-0">
 <span class="block truncate text-sm font-medium text-gray-900">{{ $suggestion->name }}</span>
 <span class="block truncate text-xs text-gray-500">&#64;{{ $suggestion->username }}</span>
 </span>
 </a>
 <a href="{{ route('profile.show', $suggestion) }}" class="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700">Follow</a>
 </li>
 @endforeach
 </ul>
 <a href="{{ route('explore.index', ['tab'=>'users']) }}" class="mt-3 inline-block text-xs font-medium text-emerald-600 hover:underline">See more</a>
</section>
